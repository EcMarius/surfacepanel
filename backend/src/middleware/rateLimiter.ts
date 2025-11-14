import rateLimit from 'express-rate-limit';
import RedisStore from 'rate-limit-redis';
import redisClient from '../config/redis';
import { config } from '../config';

// General API rate limiter
export const apiLimiter = rateLimit({
  store: new RedisStore({
    // @ts-ignore - Known typing issue
    client: redisClient,
    prefix: 'rl:api:',
  }),
  windowMs: config.security.rateLimitWindowMs, // 15 minutes
  max: config.security.rateLimitMaxRequests, // limit each IP
  message: {
    success: false,
    error: {
      code: 'RATE_LIMIT_ERROR',
      message: 'Too many requests, please try again later',
    },
    timestamp: new Date().toISOString(),
  },
  standardHeaders: true,
  legacyHeaders: false,
});

// Strict limiter for authentication endpoints
export const authLimiter = rateLimit({
  store: new RedisStore({
    // @ts-ignore
    client: redisClient,
    prefix: 'rl:auth:',
  }),
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 5, // limit each IP to 5 requests per windowMs
  skipSuccessfulRequests: true, // don't count successful logins
  message: {
    success: false,
    error: {
      code: 'RATE_LIMIT_ERROR',
      message: 'Too many authentication attempts, please try again later',
    },
    timestamp: new Date().toISOString(),
  },
});

// Create resource limiter
export const createLimiter = rateLimit({
  store: new RedisStore({
    // @ts-ignore
    client: redisClient,
    prefix: 'rl:create:',
  }),
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 20, // limit each IP to 20 create operations per hour
  message: {
    success: false,
    error: {
      code: 'RATE_LIMIT_ERROR',
      message: 'Too many create operations, please try again later',
    },
    timestamp: new Date().toISOString(),
  },
});

// File upload limiter
export const uploadLimiter = rateLimit({
  store: new RedisStore({
    // @ts-ignore
    client: redisClient,
    prefix: 'rl:upload:',
  }),
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 10, // limit each IP to 10 uploads per 15 minutes
  message: {
    success: false,
    error: {
      code: 'RATE_LIMIT_ERROR',
      message: 'Too many file uploads, please try again later',
    },
    timestamp: new Date().toISOString(),
  },
});
