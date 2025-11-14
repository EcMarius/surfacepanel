import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/create', authorize('backups.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.post('/:id/restore', authorize('backups.restore'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

router.delete('/:id', authorize('backups.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
