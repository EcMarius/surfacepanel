import express from 'express';
import { authenticate, authorize } from '../middleware/auth';
import { asyncHandler } from '../middleware/errorHandler';

const router = express.Router();
router.use(authenticate);

router.get('/config/:domainId', asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

router.put('/config/:domainId', authorize('domains.update'), asyncHandler(async (req, res) => {
  res.json({ success: true, data: {} });
}));

export default router;
