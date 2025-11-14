import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import authService from '../services/auth.service';
import { sendSuccess } from '../utils/response';

export class AuthController {
  async register(req: AuthRequest, res: Response) {
    const result = await authService.register(req.body);

    return sendSuccess(
      res,
      {
        user: result.user,
        message: result.verificationToken
          ? 'Registration successful. Please check your email to verify your account.'
          : 'Registration successful.',
      },
      'User registered successfully',
      201
    );
  }

  async login(req: AuthRequest, res: Response) {
    const { email, password, two_factor_code } = req.body;

    const result = await authService.login(email, password, two_factor_code);

    return sendSuccess(
      res,
      result,
      'Login successful'
    );
  }

  async refreshToken(req: AuthRequest, res: Response) {
    const { refresh_token } = req.body;

    const tokens = await authService.refreshToken(refresh_token);

    return sendSuccess(
      res,
      { tokens },
      'Token refreshed successfully'
    );
  }

  async logout(req: AuthRequest, res: Response) {
    const { refresh_token } = req.body;
    const accessToken = req.headers.authorization?.substring(7) || '';

    await authService.logout(req.user!.id, refresh_token, accessToken);

    return sendSuccess(
      res,
      null,
      'Logout successful'
    );
  }

  async forgotPassword(req: AuthRequest, res: Response) {
    const { email } = req.body;

    await authService.forgotPassword(email);

    return sendSuccess(
      res,
      null,
      'If an account with that email exists, a password reset link has been sent'
    );
  }

  async resetPassword(req: AuthRequest, res: Response) {
    const { token, password } = req.body;

    await authService.resetPassword(token, password);

    return sendSuccess(
      res,
      null,
      'Password reset successful'
    );
  }

  async changePassword(req: AuthRequest, res: Response) {
    const { current_password, new_password } = req.body;

    await authService.changePassword(req.user!.id, current_password, new_password);

    return sendSuccess(
      res,
      null,
      'Password changed successfully'
    );
  }

  async verifyEmail(req: AuthRequest, res: Response) {
    const { token } = req.body;

    await authService.verifyEmail(token);

    return sendSuccess(
      res,
      null,
      'Email verified successfully'
    );
  }

  async getProfile(req: AuthRequest, res: Response) {
    return sendSuccess(
      res,
      { user: req.user },
      'Profile retrieved successfully'
    );
  }

  async enable2FA(req: AuthRequest, res: Response) {
    const { password } = req.body;

    const result = await authService.enable2FA(req.user!.id, password);

    return sendSuccess(
      res,
      result,
      'Scan the QR code with your authenticator app'
    );
  }

  async verify2FA(req: AuthRequest, res: Response) {
    const { token } = req.body;

    await authService.verify2FA(req.user!.id, token);

    return sendSuccess(
      res,
      null,
      'Two-factor authentication enabled successfully'
    );
  }

  async disable2FA(req: AuthRequest, res: Response) {
    const { password, token } = req.body;

    await authService.disable2FA(req.user!.id, password, token);

    return sendSuccess(
      res,
      null,
      'Two-factor authentication disabled successfully'
    );
  }
}

export default new AuthController();
