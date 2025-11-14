import express, { Application } from 'express';
import cors from 'cors';
import helmet from 'helmet';
import compression from 'compression';
import morgan from 'morgan';
import 'express-async-errors';
import { config } from './config';
import logger from './config/logger';
import { errorHandler, notFoundHandler } from './middleware/errorHandler';
import { apiLimiter } from './middleware/rateLimiter';

// Import routes
import authRoutes from './routes/auth.routes';
import userRoutes from './routes/user.routes';
import domainRoutes from './routes/domain.routes';
import dnsRoutes from './routes/dns.routes';
import databaseRoutes from './routes/database.routes';
import emailRoutes from './routes/email.routes';
import sslRoutes from './routes/ssl.routes';
import fileRoutes from './routes/file.routes';
import ftpRoutes from './routes/ftp.routes';
import cronRoutes from './routes/cron.routes';
import backupRoutes from './routes/backup.routes';
import firewallRoutes from './routes/firewall.routes';
import monitoringRoutes from './routes/monitoring.routes';
import webServerRoutes from './routes/webserver.routes';
import settingsRoutes from './routes/settings.routes';
import auditRoutes from './routes/audit.routes';

const app: Application = express();

// Security middleware
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      scriptSrc: ["'self'"],
      imgSrc: ["'self'", 'data:', 'https:'],
    },
  },
  hsts: {
    maxAge: 31536000,
    includeSubDomains: true,
    preload: true,
  },
}));

// CORS
app.use(cors({
  origin: config.cors.origin,
  credentials: true,
}));

// Compression
app.use(compression());

// Body parsers
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Logging
const morganFormat = config.nodeEnv === 'production' ? 'combined' : 'dev';
app.use(morgan(morganFormat, {
  stream: {
    write: (message: string) => logger.http(message.trim()),
  },
}));

// Health check
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

// API routes
const apiPrefix = `/api/${config.apiVersion}`;

app.use(`${apiPrefix}/auth`, authRoutes);
app.use(`${apiPrefix}/users`, apiLimiter, userRoutes);
app.use(`${apiPrefix}/domains`, apiLimiter, domainRoutes);
app.use(`${apiPrefix}/dns`, apiLimiter, dnsRoutes);
app.use(`${apiPrefix}/databases`, apiLimiter, databaseRoutes);
app.use(`${apiPrefix}/email`, apiLimiter, emailRoutes);
app.use(`${apiPrefix}/ssl`, apiLimiter, sslRoutes);
app.use(`${apiPrefix}/files`, apiLimiter, fileRoutes);
app.use(`${apiPrefix}/ftp`, apiLimiter, ftpRoutes);
app.use(`${apiPrefix}/cron`, apiLimiter, cronRoutes);
app.use(`${apiPrefix}/backups`, apiLimiter, backupRoutes);
app.use(`${apiPrefix}/firewall`, apiLimiter, firewallRoutes);
app.use(`${apiPrefix}/monitoring`, apiLimiter, monitoringRoutes);
app.use(`${apiPrefix}/webserver`, apiLimiter, webServerRoutes);
app.use(`${apiPrefix}/settings`, apiLimiter, settingsRoutes);
app.use(`${apiPrefix}/audit`, apiLimiter, auditRoutes);

// 404 handler
app.use(notFoundHandler);

// Error handler (must be last)
app.use(errorHandler);

export default app;
