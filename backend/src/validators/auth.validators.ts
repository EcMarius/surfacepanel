import Joi from 'joi';

export const registerSchema = Joi.object({
  email: Joi.string().email().required().lowercase().trim(),
  password: Joi.string().min(8).required(),
  first_name: Joi.string().min(2).max(100).trim(),
  last_name: Joi.string().min(2).max(100).trim(),
  role: Joi.string().valid('user', 'reseller').default('user'),
});

export const loginSchema = Joi.object({
  email: Joi.string().email().required().lowercase().trim(),
  password: Joi.string().required(),
  two_factor_code: Joi.string().length(6).pattern(/^[0-9]+$/),
});

export const refreshTokenSchema = Joi.object({
  refresh_token: Joi.string().required(),
});

export const forgotPasswordSchema = Joi.object({
  email: Joi.string().email().required().lowercase().trim(),
});

export const resetPasswordSchema = Joi.object({
  token: Joi.string().required(),
  password: Joi.string().min(8).required(),
});

export const changePasswordSchema = Joi.object({
  current_password: Joi.string().required(),
  new_password: Joi.string().min(8).required(),
});

export const verifyEmailSchema = Joi.object({
  token: Joi.string().required(),
});

export const enable2FASchema = Joi.object({
  password: Joi.string().required(),
});

export const verify2FASchema = Joi.object({
  token: Joi.string().length(6).pattern(/^[0-9]+$/).required(),
});

export const disable2FASchema = Joi.object({
  password: Joi.string().required(),
  token: Joi.string().length(6).pattern(/^[0-9]+$/).required(),
});
