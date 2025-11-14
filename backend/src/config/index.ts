import dotenv from 'dotenv';
import path from 'path';

dotenv.config();

export const config = {
  // Application
  nodeEnv: process.env.NODE_ENV || 'development',
  port: parseInt(process.env.PORT || '5000', 10),
  apiVersion: process.env.API_VERSION || 'v1',

  // Database
  database: {
    url: process.env.DATABASE_URL || 'postgresql://surfacepanel:surfacepanel@localhost:5432/surfacepanel',
    pool: {
      min: parseInt(process.env.DATABASE_POOL_MIN || '2', 10),
      max: parseInt(process.env.DATABASE_POOL_MAX || '10', 10),
    },
  },

  // Redis
  redis: {
    url: process.env.REDIS_URL || 'redis://localhost:6379',
    password: process.env.REDIS_PASSWORD,
    db: parseInt(process.env.REDIS_DB || '0', 10),
  },

  // JWT
  jwt: {
    secret: process.env.JWT_SECRET || 'your-secret-key-change-in-production',
    refreshSecret: process.env.JWT_REFRESH_SECRET || 'your-refresh-secret-key-change-in-production',
    accessExpiration: process.env.JWT_ACCESS_EXPIRATION || '15m',
    refreshExpiration: process.env.JWT_REFRESH_EXPIRATION || '7d',
  },

  // Security
  security: {
    bcryptRounds: parseInt(process.env.BCRYPT_ROUNDS || '12', 10),
    rateLimitWindowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS || '900000', 10),
    rateLimitMaxRequests: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS || '100', 10),
    sessionSecret: process.env.SESSION_SECRET || 'your-session-secret-change-in-production',
  },

  // Email
  email: {
    host: process.env.SMTP_HOST || 'smtp.example.com',
    port: parseInt(process.env.SMTP_PORT || '587', 10),
    secure: process.env.SMTP_SECURE === 'true',
    auth: {
      user: process.env.SMTP_USER || '',
      pass: process.env.SMTP_PASSWORD || '',
    },
    from: process.env.SMTP_FROM || 'noreply@surfacepanel.com',
  },

  // File Upload
  upload: {
    maxFileSize: parseInt(process.env.MAX_FILE_SIZE || '104857600', 10), // 100MB
    uploadDir: process.env.UPLOAD_DIR || path.join(__dirname, '../../uploads'),
  },

  // User Files
  userFiles: {
    baseDir: process.env.USER_FILES_DIR || '/var/surfacepanel/users',
    defaultQuota: parseInt(process.env.USER_DEFAULT_QUOTA || '10737418240', 10), // 10GB
  },

  // Backups
  backup: {
    dir: process.env.BACKUP_DIR || '/var/surfacepanel/backups',
    retentionDays: parseInt(process.env.BACKUP_RETENTION_DAYS || '30', 10),
  },

  // SSL/TLS
  ssl: {
    acmeEmail: process.env.ACME_EMAIL || 'admin@example.com',
    acmeDirectory: process.env.ACME_DIRECTORY || 'https://acme-v02.api.letsencrypt.org/directory',
  },

  // DNS
  dns: {
    server: process.env.DNS_SERVER || '127.0.0.1',
    zoneDir: process.env.DNS_ZONE_DIR || '/etc/bind/zones',
  },

  // Web Server
  webServer: {
    apache: {
      sitesAvailable: process.env.APACHE_SITES_DIR || '/etc/apache2/sites-available',
      sitesEnabled: process.env.APACHE_SITES_ENABLED || '/etc/apache2/sites-enabled',
    },
    nginx: {
      sitesAvailable: process.env.NGINX_SITES_DIR || '/etc/nginx/sites-available',
      sitesEnabled: process.env.NGINX_SITES_ENABLED || '/etc/nginx/sites-enabled',
    },
  },

  // FTP
  ftp: {
    port: parseInt(process.env.FTP_PORT || '21', 10),
    pasvMin: parseInt(process.env.FTP_PASV_MIN || '30000', 10),
    pasvMax: parseInt(process.env.FTP_PASV_MAX || '31000', 10),
  },

  // MySQL (for user databases)
  mysql: {
    rootPassword: process.env.MYSQL_ROOT_PASSWORD || 'your-mysql-root-password',
    host: process.env.MYSQL_HOST || 'localhost',
    port: parseInt(process.env.MYSQL_PORT || '3306', 10),
  },

  // Monitoring
  monitoring: {
    enabled: process.env.ENABLE_MONITORING === 'true',
    interval: parseInt(process.env.MONITORING_INTERVAL || '60000', 10),
  },

  // Logging
  logging: {
    level: process.env.LOG_LEVEL || 'info',
    dir: process.env.LOG_DIR || path.join(__dirname, '../../logs'),
  },

  // CORS
  cors: {
    origin: process.env.CORS_ORIGIN || 'http://localhost:3000',
  },

  // Frontend
  frontendUrl: process.env.FRONTEND_URL || 'http://localhost:3000',

  // Admin
  admin: {
    defaultEmail: process.env.DEFAULT_ADMIN_EMAIL || 'admin@surfacepanel.local',
    defaultPassword: process.env.DEFAULT_ADMIN_PASSWORD || 'changeme123',
  },

  // Features
  features: {
    enable2FA: process.env.ENABLE_2FA !== 'false',
    enableEmailVerification: process.env.ENABLE_EMAIL_VERIFICATION === 'true',
    enableRegistration: process.env.ENABLE_REGISTRATION === 'true',
    enableAutoSSL: process.env.ENABLE_AUTO_SSL !== 'false',
  },

  // AWS S3
  aws: {
    accessKeyId: process.env.AWS_ACCESS_KEY_ID,
    secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY,
    s3Bucket: process.env.AWS_S3_BUCKET,
    s3Region: process.env.AWS_S3_REGION || 'us-east-1',
  },

  // Cloudflare
  cloudflare: {
    apiToken: process.env.CLOUDFLARE_API_TOKEN,
  },
};

export default config;
