import express from 'express';
import { authenticate } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/system', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.get('/resources', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.get('/services', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

export default router;
