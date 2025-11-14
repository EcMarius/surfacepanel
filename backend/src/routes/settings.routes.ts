import express from 'express';
import { authenticate, requireRole } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.put('/:key', requireRole('admin'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

export default router;
