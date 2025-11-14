import { Request, Response, NextFunction } from 'express';
import { verifyAccessToken, TokenPayload } from '../utils/jwt';
import { AuthenticationError, AuthorizationError } from '../utils/errors';
import db from '../config/database';
import redisClient from '../config/redis';

export interface AuthRequest extends Request {
  user?: {
    id: number;
    email: string;
    role: string;
    permissions: string[];
  };
}

export const authenticate = async (
  req: AuthRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new AuthenticationError('No token provided');
    }

    const token = authHeader.substring(7);

    // Check if token is blacklisted
    const isBlacklisted = await redisClient.get(`blacklist:${token}`);
    if (isBlacklisted) {
      throw new AuthenticationError('Token has been revoked');
    }

    const payload: TokenPayload = verifyAccessToken(token);

    // Fetch user with role and permissions
    const user = await db('users')
      .select(
        'users.id',
        'users.email',
        'users.is_active',
        'users.is_verified',
        'roles.name as role'
      )
      .join('roles', 'users.role_id', 'roles.id')
      .where('users.id', payload.userId)
      .first();

    if (!user) {
      throw new AuthenticationError('User not found');
    }

    if (!user.is_active) {
      throw new AuthenticationError('Account is inactive');
    }

    // Fetch user permissions
    const permissions = await db('permissions')
      .select('permissions.name')
      .join('role_permissions', 'permissions.id', 'role_permissions.permission_id')
      .join('roles', 'role_permissions.role_id', 'roles.id')
      .where('roles.name', user.role);

    req.user = {
      id: user.id,
      email: user.email,
      role: user.role,
      permissions: permissions.map((p: any) => p.name),
    };

    next();
  } catch (error) {
    next(error);
  }
};

export const authorize = (...requiredPermissions: string[]) => {
  return (req: AuthRequest, res: Response, next: NextFunction): void => {
    try {
      if (!req.user) {
        throw new AuthenticationError('User not authenticated');
      }

      // Admin has all permissions
      if (req.user.role === 'admin') {
        return next();
      }

      const hasPermission = requiredPermissions.some(permission =>
        req.user!.permissions.includes(permission)
      );

      if (!hasPermission) {
        throw new AuthorizationError(
          `Missing required permission: ${requiredPermissions.join(' or ')}`
        );
      }

      next();
    } catch (error) {
      next(error);
    }
  };
};

export const requireRole = (...roles: string[]) => {
  return (req: AuthRequest, res: Response, next: NextFunction): void => {
    try {
      if (!req.user) {
        throw new AuthenticationError('User not authenticated');
      }

      if (!roles.includes(req.user.role)) {
        throw new AuthorizationError(
          `Required role: ${roles.join(' or ')}`
        );
      }

      next();
    } catch (error) {
      next(error);
    }
  };
};

// Optional authentication - doesn't fail if no token
export const optionalAuth = async (
  req: AuthRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const authHeader = req.headers.authorization;

    if (authHeader && authHeader.startsWith('Bearer ')) {
      const token = authHeader.substring(7);

      const isBlacklisted = await redisClient.get(`blacklist:${token}`);
      if (!isBlacklisted) {
        const payload: TokenPayload = verifyAccessToken(token);

        const user = await db('users')
          .select('users.id', 'users.email', 'roles.name as role')
          .join('roles', 'users.role_id', 'roles.id')
          .where('users.id', payload.userId)
          .where('users.is_active', true)
          .first();

        if (user) {
          const permissions = await db('permissions')
            .select('permissions.name')
            .join('role_permissions', 'permissions.id', 'role_permissions.permission_id')
            .join('roles', 'role_permissions.role_id', 'roles.id')
            .where('roles.name', user.role);

          req.user = {
            id: user.id,
            email: user.email,
            role: user.role,
            permissions: permissions.map((p: any) => p.name),
          };
        }
      }
    }

    next();
  } catch (error) {
    // Don't fail, just continue without user
    next();
  }
};
