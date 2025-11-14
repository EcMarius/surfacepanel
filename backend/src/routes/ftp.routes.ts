import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/', authorize('ftp.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:id', authorize('ftp.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
