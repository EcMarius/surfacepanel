import express from 'express';
import { authenticate, requireRole } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);
router.use(requireRole('admin'));

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.get('/:id', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

export default router;
