import express from 'express';
import authController from '../controllers/auth.controller';
import { authenticate } from '../middleware/auth';
import { validate } from '../middleware/validate';
import { authLimiter } from '../middleware/rateLimiter';
import { asyncHandler } from '../middleware/errorHandler';
import {
  registerSchema,
  loginSchema,
  refreshTokenSchema,
  forgotPasswordSchema,
  resetPasswordSchema,
  changePasswordSchema,
  verifyEmailSchema,
  enable2FASchema,
  verify2FASchema,
  disable2FASchema,
} from '../validators/auth.validators';

const router = express.Router();

// Public routes
router.post(
  '/register',
  authLimiter,
  validate(registerSchema),
  asyncHandler(authController.register)
);

router.post(
  '/login',
  authLimiter,
  validate(loginSchema),
  asyncHandler(authController.login)
);

router.post(
  '/refresh',
  validate(refreshTokenSchema),
  asyncHandler(authController.refreshToken)
);

router.post(
  '/forgot-password',
  authLimiter,
  validate(forgotPasswordSchema),
  asyncHandler(authController.forgotPassword)
);

router.post(
  '/reset-password',
  authLimiter,
  validate(resetPasswordSchema),
  asyncHandler(authController.resetPassword)
);

router.post(
  '/verify-email',
  validate(verifyEmailSchema),
  asyncHandler(authController.verifyEmail)
);

// Protected routes
router.post(
  '/logout',
  authenticate,
  validate(refreshTokenSchema),
  asyncHandler(authController.logout)
);

router.get(
  '/profile',
  authenticate,
  asyncHandler(authController.getProfile)
);

router.post(
  '/change-password',
  authenticate,
  validate(changePasswordSchema),
  asyncHandler(authController.changePassword)
);

// 2FA routes
router.post(
  '/2fa/enable',
  authenticate,
  validate(enable2FASchema),
  asyncHandler(authController.enable2FA)
);

router.post(
  '/2fa/verify',
  authenticate,
  validate(verify2FASchema),
  asyncHandler(authController.verify2FA)
);

router.post(
  '/2fa/disable',
  authenticate,
  validate(disable2FASchema),
  asyncHandler(authController.disable2FA)
);

export default router;
