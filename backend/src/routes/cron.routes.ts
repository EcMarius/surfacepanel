import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/', authorize('cron.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.put('/:id', authorize('cron.update'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:id', authorize('cron.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
