import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/upload', authorize('files.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.get('/download/:path', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/:path', authorize('files.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
