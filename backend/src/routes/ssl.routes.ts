import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/install', authorize('ssl.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.post('/letsencrypt', authorize('ssl.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:id', authorize('ssl.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
