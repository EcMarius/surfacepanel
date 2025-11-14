import express from 'express';
import { authenticate, requireRole } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);
router.use(requireRole('admin'));

router.get('/rules', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/rules', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/rules/:id', asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
