import { Request, Response, NextFunction } from 'express';
import { AppError } from '../utils/errors';
import logger from '../config/logger';
import { sendError } from '../utils/response';

export const errorHandler = (
  err: Error | AppError,
  req: Request,
  res: Response,
  next: NextFunction
): Response => {
  // Log error
  logger.error('Error occurred:', {
    message: err.message,
    stack: err.stack,
    path: req.path,
    method: req.method,
    ip: req.ip,
    user: (req as any).user?.email,
  });

  // Handle AppError (operational errors)
  if (err instanceof AppError) {
    return sendError(res, err.code, err.message, err.statusCode, err.details);
  }

  // Handle Joi validation errors
  if ((err as any).isJoi) {
    const details = (err as any).details.map((detail: any) => ({
      field: detail.path.join('.'),
      message: detail.message,
    }));
    return sendError(res, 'VALIDATION_ERROR', 'Validation failed', 400, details);
  }

  // Handle database errors
  if ((err as any).code) {
    switch ((err as any).code) {
      case '23505': // Unique violation
        return sendError(res, 'CONFLICT_ERROR', 'Resource already exists', 409);
      case '23503': // Foreign key violation
        return sendError(res, 'REFERENCE_ERROR', 'Referenced resource not found', 400);
      case '23502': // Not null violation
        return sendError(res, 'VALIDATION_ERROR', 'Required field missing', 400);
      case '22P02': // Invalid text representation
        return sendError(res, 'VALIDATION_ERROR', 'Invalid data format', 400);
    }
  }

  // Handle JWT errors
  if (err.name === 'JsonWebTokenError') {
    return sendError(res, 'AUTHENTICATION_ERROR', 'Invalid token', 401);
  }
  if (err.name === 'TokenExpiredError') {
    return sendError(res, 'AUTHENTICATION_ERROR', 'Token expired', 401);
  }

  // Handle multer errors (file upload)
  if ((err as any).name === 'MulterError') {
    if ((err as any).code === 'LIMIT_FILE_SIZE') {
      return sendError(res, 'VALIDATION_ERROR', 'File too large', 400);
    }
    return sendError(res, 'UPLOAD_ERROR', err.message, 400);
  }

  // Handle unexpected errors
  return sendError(
    res,
    'INTERNAL_ERROR',
    process.env.NODE_ENV === 'production'
      ? 'An unexpected error occurred'
      : err.message,
    500
  );
};

// Handle 404 errors
export const notFoundHandler = (
  req: Request,
  res: Response,
  next: NextFunction
): Response => {
  return sendError(res, 'NOT_FOUND', 'Route not found', 404);
};

// Async error wrapper
export const asyncHandler = (
  fn: (req: Request, res: Response, next: NextFunction) => Promise<any>
) => {
  return (req: Request, res: Response, next: NextFunction) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
};
