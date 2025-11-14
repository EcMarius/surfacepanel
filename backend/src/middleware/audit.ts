import { Request, Response, NextFunction } from 'express';
import { AuthRequest } from './auth';
import db from '../config/database';
import logger from '../config/logger';

export const auditLog = (action: string, resource: string) => {
  return async (req: AuthRequest, res: Response, next: NextFunction): Promise<void> => {
    const originalSend = res.json;

    // Override res.json to capture the response
    res.json = function (data: any): Response {
      // Restore original method
      res.json = originalSend;

      // Log audit if operation was successful
      if (data.success && req.user) {
        const resourceId = req.params.id || data.data?.id;

        setImmediate(async () => {
          try {
            await db('audit_logs').insert({
              user_id: req.user!.id,
              action,
              resource,
              resource_id: resourceId,
              old_values: (req as any).oldValues || null,
              new_values: req.method === 'DELETE' ? null : req.body || null,
              ip_address: req.ip,
              user_agent: req.get('user-agent'),
            });
          } catch (error) {
            logger.error('Failed to create audit log:', error);
          }
        });
      }

      return originalSend.call(this, data);
    };

    next();
  };
};

// Store old values for update operations
export const captureOldValues = (tableName: string, idParam: string = 'id') => {
  return async (req: AuthRequest, res: Response, next: NextFunction): Promise<void> => {
    try {
      const id = req.params[idParam];

      if (id && (req.method === 'PUT' || req.method === 'PATCH' || req.method === 'DELETE')) {
        const oldRecord = await db(tableName)
          .where('id', id)
          .first();

        if (oldRecord) {
          (req as any).oldValues = oldRecord;
        }
      }

      next();
    } catch (error) {
      logger.error('Failed to capture old values:', error);
      next();
    }
  };
};
