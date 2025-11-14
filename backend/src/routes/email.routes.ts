import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/accounts', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/accounts', authorize('email.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.delete('/accounts/:id', authorize('email.delete'), asyncHandler(async (req, res) => {
  res.json({ success: true });
}));

router.get('/forwarders', asyncHandler(async (req, res) => {
  res.json({ success: true, data: [] });
}));

router.post('/forwarders', authorize('email.create'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

export default router;
