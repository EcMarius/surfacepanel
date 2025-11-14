import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/', authorize('databases.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.get('/:id', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:id', authorize('databases.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

router.get('/:id/users', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/:id/users', authorize('databases.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

export default router;
