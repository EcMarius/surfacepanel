import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/zones', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.get('/zones/:id/records', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/zones/:id/records', authorize('dns.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.put('/records/:id', authorize('dns.update'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/records/:id', authorize('dns.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

export default router;
