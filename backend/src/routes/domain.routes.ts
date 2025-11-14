import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/', authorize('domains.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.get('/:id', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.put('/:id', authorize('domains.update'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:id', authorize('domains.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
