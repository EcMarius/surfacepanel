import crypto from 'crypto';
import speakeasy from 'speakeasy';
import QRCode from 'qrcode';
import db from '../config/database';
import redisClient from '../config/redis';
import { hashPassword, comparePassword, validatePasswordStrength } from '../utils/password';
import { generateTokenPair, verifyRefreshToken, TokenPayload } from '../utils/jwt';
import {
  AuthenticationError,
  ValidationError,
  ConflictError,
  NotFoundError,
} from '../utils/errors';
import { config } from '../config';
import logger from '../config/logger';

export class AuthService {
  async register(data: {
    email: string;
    password: string;
    first_name?: string;
    last_name?: string;
    role?: string;
  }) {
    // Check if registration is enabled
    const registrationEnabled = await db('settings')
      .where('key', 'enable_registration')
      .first();

    if (registrationEnabled && registrationEnabled.value === 'false') {
      throw new ValidationError('Registration is currently disabled');
    }

    // Validate password strength
    const passwordValidation = validatePasswordStrength(data.password);
    if (!passwordValidation.isValid) {
      throw new ValidationError('Password does not meet requirements', passwordValidation.errors);
    }

    // Check if user already exists
    const existingUser = await db('users')
      .where('email', data.email)
      .first();

    if (existingUser) {
      throw new ConflictError('User with this email already exists');
    }

    // Hash password
    const passwordHash = await hashPassword(data.password);

    // Get role ID
    const role = await db('roles')
      .where('name', data.role || 'user')
      .first();

    if (!role) {
      throw new NotFoundError('Role');
    }

    // Generate verification token
    const verificationToken = crypto.randomBytes(32).toString('hex');
    const verificationExpires = new Date(Date.now() + 24 * 60 * 60 * 1000); // 24 hours

    // Create user
    const [user] = await db('users')
      .insert({
        email: data.email,
        password_hash: passwordHash,
        first_name: data.first_name,
        last_name: data.last_name,
        role_id: role.id,
        verification_token: verificationToken,
        verification_token_expires: verificationExpires,
        is_verified: !config.features.enableEmailVerification, // Auto-verify if email verification disabled
      })
      .returning(['id', 'email', 'first_name', 'last_name', 'is_verified']);

    // Create quota for user
    const defaultDiskQuota = parseInt(await this.getSetting('default_disk_quota'), 10);
    const defaultBandwidthQuota = parseInt(await this.getSetting('default_bandwidth_quota'), 10);

    await db('quotas').insert({
      user_id: user.id,
      disk_space_limit: defaultDiskQuota,
      bandwidth_limit: defaultBandwidthQuota,
    });

    logger.info(`New user registered: ${user.email}`);

    return {
      user,
      verificationToken: config.features.enableEmailVerification ? verificationToken : undefined,
    };
  }

  async login(email: string, password: string, twoFactorCode?: string) {
    // Find user
    const user = await db('users')
      .select('users.*', 'roles.name as role_name')
      .join('roles', 'users.role_id', 'roles.id')
      .where('users.email', email)
      .first();

    if (!user) {
      throw new AuthenticationError('Invalid credentials');
    }

    // Check if account is locked
    if (user.locked_until && new Date(user.locked_until) > new Date()) {
      const remainingTime = Math.ceil((new Date(user.locked_until).getTime() - Date.now()) / 1000 / 60);
      throw new AuthenticationError(`Account locked. Try again in ${remainingTime} minutes`);
    }

    // Verify password
    const isPasswordValid = await comparePassword(password, user.password_hash);

    if (!isPasswordValid) {
      await this.handleFailedLogin(user.id, user.failed_login_attempts);
      throw new AuthenticationError('Invalid credentials');
    }

    // Check if account is active
    if (!user.is_active) {
      throw new AuthenticationError('Account is inactive');
    }

    // Check if email is verified
    if (!user.is_verified && config.features.enableEmailVerification) {
      throw new AuthenticationError('Please verify your email address');
    }

    // Check 2FA if enabled
    if (user.two_factor_enabled) {
      if (!twoFactorCode) {
        throw new AuthenticationError('Two-factor authentication code required');
      }

      const isValid = speakeasy.totp.verify({
        secret: user.two_factor_secret,
        encoding: 'base32',
        token: twoFactorCode,
        window: 2, // Allow 2 steps before/after
      });

      if (!isValid) {
        throw new AuthenticationError('Invalid two-factor authentication code');
      }
    }

    // Reset failed login attempts and update last login
    await db('users')
      .where('id', user.id)
      .update({
        failed_login_attempts: 0,
        locked_until: null,
        last_login: db.fn.now(),
      });

    // Generate tokens
    const tokenPayload: TokenPayload = {
      userId: user.id,
      email: user.email,
      role: user.role_name,
    };

    const tokens = generateTokenPair(tokenPayload);

    // Store refresh token in database
    await db('sessions').insert({
      user_id: user.id,
      refresh_token: tokens.refreshToken,
      expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000), // 7 days
    });

    logger.info(`User logged in: ${user.email}`);

    return {
      user: {
        id: user.id,
        email: user.email,
        first_name: user.first_name,
        last_name: user.last_name,
        role: user.role_name,
      },
      tokens,
    };
  }

  async refreshToken(refreshToken: string) {
    const payload = verifyRefreshToken(refreshToken);

    // Check if refresh token exists in database
    const session = await db('sessions')
      .where('refresh_token', refreshToken)
      .where('expires_at', '>', db.fn.now())
      .first();

    if (!session) {
      throw new AuthenticationError('Invalid refresh token');
    }

    // Get user
    const user = await db('users')
      .select('users.*', 'roles.name as role_name')
      .join('roles', 'users.role_id', 'roles.id')
      .where('users.id', payload.userId)
      .where('users.is_active', true)
      .first();

    if (!user) {
      throw new AuthenticationError('User not found or inactive');
    }

    // Generate new tokens
    const tokenPayload: TokenPayload = {
      userId: user.id,
      email: user.email,
      role: user.role_name,
    };

    const tokens = generateTokenPair(tokenPayload);

    // Update session with new refresh token
    await db('sessions')
      .where('id', session.id)
      .update({
        refresh_token: tokens.refreshToken,
        expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
      });

    return tokens;
  }

  async logout(userId: number, refreshToken: string, accessToken: string) {
    // Delete session
    await db('sessions')
      .where('user_id', userId)
      .where('refresh_token', refreshToken)
      .delete();

    // Blacklist access token
    const ttl = 15 * 60; // 15 minutes
    await redisClient.setex(`blacklist:${accessToken}`, ttl, 'true');

    logger.info(`User logged out: ${userId}`);
  }

  async forgotPassword(email: string) {
    const user = await db('users')
      .where('email', email)
      .first();

    if (!user) {
      // Don't reveal if user exists
      return { success: true };
    }

    const resetToken = crypto.randomBytes(32).toString('hex');
    const resetExpires = new Date(Date.now() + 60 * 60 * 1000); // 1 hour

    await db('users')
      .where('id', user.id)
      .update({
        reset_password_token: resetToken,
        reset_password_expires: resetExpires,
      });

    logger.info(`Password reset requested for: ${email}`);

    return {
      success: true,
      resetToken,
    };
  }

  async resetPassword(token: string, newPassword: string) {
    const user = await db('users')
      .where('reset_password_token', token)
      .where('reset_password_expires', '>', db.fn.now())
      .first();

    if (!user) {
      throw new ValidationError('Invalid or expired reset token');
    }

    // Validate password strength
    const passwordValidation = validatePasswordStrength(newPassword);
    if (!passwordValidation.isValid) {
      throw new ValidationError('Password does not meet requirements', passwordValidation.errors);
    }

    const passwordHash = await hashPassword(newPassword);

    await db('users')
      .where('id', user.id)
      .update({
        password_hash: passwordHash,
        reset_password_token: null,
        reset_password_expires: null,
      });

    // Invalidate all sessions
    await db('sessions')
      .where('user_id', user.id)
      .delete();

    logger.info(`Password reset for user: ${user.email}`);

    return { success: true };
  }

  async changePassword(userId: number, currentPassword: string, newPassword: string) {
    const user = await db('users')
      .where('id', userId)
      .first();

    if (!user) {
      throw new NotFoundError('User');
    }

    // Verify current password
    const isValid = await comparePassword(currentPassword, user.password_hash);
    if (!isValid) {
      throw new AuthenticationError('Current password is incorrect');
    }

    // Validate new password strength
    const passwordValidation = validatePasswordStrength(newPassword);
    if (!passwordValidation.isValid) {
      throw new ValidationError('Password does not meet requirements', passwordValidation.errors);
    }

    const passwordHash = await hashPassword(newPassword);

    await db('users')
      .where('id', userId)
      .update({ password_hash: passwordHash });

    logger.info(`Password changed for user: ${user.email}`);

    return { success: true };
  }

  async verifyEmail(token: string) {
    const user = await db('users')
      .where('verification_token', token)
      .where('verification_token_expires', '>', db.fn.now())
      .first();

    if (!user) {
      throw new ValidationError('Invalid or expired verification token');
    }

    await db('users')
      .where('id', user.id)
      .update({
        is_verified: true,
        verification_token: null,
        verification_token_expires: null,
      });

    logger.info(`Email verified for user: ${user.email}`);

    return { success: true };
  }

  async enable2FA(userId: number, password: string) {
    const user = await db('users')
      .where('id', userId)
      .first();

    if (!user) {
      throw new NotFoundError('User');
    }

    // Verify password
    const isValid = await comparePassword(password, user.password_hash);
    if (!isValid) {
      throw new AuthenticationError('Invalid password');
    }

    if (user.two_factor_enabled) {
      throw new ConflictError('Two-factor authentication is already enabled');
    }

    // Generate secret
    const secret = speakeasy.generateSecret({
      name: `SurfacePanel (${user.email})`,
      issuer: 'SurfacePanel',
    });

    // Generate QR code
    const qrCode = await QRCode.toDataURL(secret.otpauth_url!);

    // Store secret temporarily (will be confirmed when user verifies)
    await redisClient.setex(
      `2fa:setup:${userId}`,
      600, // 10 minutes
      secret.base32
    );

    return {
      secret: secret.base32,
      qrCode,
    };
  }

  async verify2FA(userId: number, token: string) {
    const secret = await redisClient.get(`2fa:setup:${userId}`);

    if (!secret) {
      throw new ValidationError('2FA setup session expired');
    }

    const isValid = speakeasy.totp.verify({
      secret,
      encoding: 'base32',
      token,
      window: 2,
    });

    if (!isValid) {
      throw new ValidationError('Invalid verification code');
    }

    // Enable 2FA
    await db('users')
      .where('id', userId)
      .update({
        two_factor_enabled: true,
        two_factor_secret: secret,
      });

    // Clean up temporary secret
    await redisClient.del(`2fa:setup:${userId}`);

    logger.info(`2FA enabled for user ID: ${userId}`);

    return { success: true };
  }

  async disable2FA(userId: number, password: string, token: string) {
    const user = await db('users')
      .where('id', userId)
      .first();

    if (!user) {
      throw new NotFoundError('User');
    }

    // Verify password
    const isPasswordValid = await comparePassword(password, user.password_hash);
    if (!isPasswordValid) {
      throw new AuthenticationError('Invalid password');
    }

    if (!user.two_factor_enabled) {
      throw new ValidationError('Two-factor authentication is not enabled');
    }

    // Verify 2FA token
    const isTokenValid = speakeasy.totp.verify({
      secret: user.two_factor_secret,
      encoding: 'base32',
      token,
      window: 2,
    });

    if (!isTokenValid) {
      throw new ValidationError('Invalid 2FA code');
    }

    // Disable 2FA
    await db('users')
      .where('id', userId)
      .update({
        two_factor_enabled: false,
        two_factor_secret: null,
      });

    logger.info(`2FA disabled for user ID: ${userId}`);

    return { success: true };
  }

  private async handleFailedLogin(userId: number, currentAttempts: number) {
    const maxAttempts = parseInt(await this.getSetting('max_login_attempts'), 10);
    const lockoutDuration = parseInt(await this.getSetting('lockout_duration'), 10);

    const newAttempts = currentAttempts + 1;

    if (newAttempts >= maxAttempts) {
      const lockedUntil = new Date(Date.now() + lockoutDuration * 1000);
      await db('users')
        .where('id', userId)
        .update({
          failed_login_attempts: newAttempts,
          locked_until: lockedUntil,
        });
    } else {
      await db('users')
        .where('id', userId)
        .update({ failed_login_attempts: newAttempts });
    }
  }

  private async getSetting(key: string): Promise<string> {
    const setting = await db('settings')
      .where('key', key)
      .first();

    return setting?.value || '';
  }
}

export default new AuthService();
