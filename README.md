# SurfacePanel

A modern, scalable web hosting control panel alternative to cPanel and DirectAdmin, built with Node.js, React, PostgreSQL, and Redis.

## Features

### Core Features
- **User Management** - Multi-tenant architecture with role-based access control (Admin, Reseller, User)
- **Authentication** - JWT-based authentication with refresh tokens and 2FA support
- **Security** - Rate limiting, IP blocking, audit logging, and comprehensive security features

### Hosting Management
- **Domain Management** - Add, configure, and manage domains with automatic DNS setup
- **DNS Management** - Full DNS zone editor supporting all record types (A, AAAA, CNAME, MX, TXT, SRV, CAA)
- **Database Management** - MySQL/MariaDB and PostgreSQL database creation and management
- **Email Management** - Email accounts, forwarders, quotas, and spam filtering
- **SSL/TLS Certificates** - Let's Encrypt integration with auto-renewal
- **File Management** - Web-based file manager with upload, download, and edit capabilities
- **FTP/SFTP** - FTP account management with quota enforcement
- **Cron Jobs** - Visual cron job manager with execution history
- **Backups** - Automated backup and restore system with remote storage support

### System Management
- **Resource Monitoring** - Real-time CPU, memory, disk, and bandwidth monitoring
- **Web Server** - Apache/Nginx configuration management
- **Firewall** - IP-based firewall rules and security policies
- **Audit Logs** - Comprehensive audit trail of all system actions
- **Settings** - System-wide configuration management

## Architecture

### Technology Stack

**Backend:**
- Node.js 20.x LTS
- Express.js 4.x
- TypeScript 5.x
- PostgreSQL 15.x
- Redis 7.x
- JWT Authentication
- Knex.js ORM

**Frontend:**
- React 18.x
- TypeScript 5.x
- Redux Toolkit
- Material-UI (MUI)
- React Router v6
- Axios

**Infrastructure:**
- Docker & Docker Compose
- Nginx (Reverse Proxy)
- PM2 (Process Manager)

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Node.js 20.x (for local development)
- Git

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/yourusername/surfacepanel.git
   cd surfacepanel
   ```

2. **Configure environment variables:**
   ```bash
   cp backend/.env.example backend/.env
   # Edit backend/.env with your configuration
   ```

3. **Start with Docker Compose (Development):**
   ```bash
   docker-compose -f docker-compose.dev.yml up
   ```

4. **Access the application:**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:5000
   - API Docs: http://localhost:5000/api/v1

5. **Default credentials:**
   - Email: admin@surfacepanel.local
   - Password: changeme123
   - **⚠️ Change these immediately in production!**

### Production Deployment

1. **Configure production environment:**
   ```bash
   cp backend/.env.example backend/.env.production
   # Set strong passwords and secrets
   ```

2. **Deploy with Docker Compose:**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

3. **Run database migrations:**
   ```bash
   docker-compose exec backend npm run migrate
   ```

4. **Seed initial data:**
   ```bash
   docker-compose exec backend npm run seed
   ```

## Development

### Local Development Setup

**Backend:**
```bash
cd backend
npm install
cp .env.example .env
npm run migrate
npm run seed
npm run dev
```

**Frontend:**
```bash
cd frontend
npm install
npm start
```

### Database Migrations

**Create a new migration:**
```bash
npm run migrate:make migration_name
```

**Run migrations:**
```bash
npm run migrate
```

**Rollback last migration:**
```bash
npm run migrate:rollback
```

### Testing

**Backend tests:**
```bash
cd backend
npm test
```

**Frontend tests:**
```bash
cd frontend
npm test
```

## API Documentation

### Authentication Endpoints

**POST /api/v1/auth/register**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "first_name": "John",
  "last_name": "Doe"
}
```

**POST /api/v1/auth/login**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "two_factor_code": "123456"
}
```

**POST /api/v1/auth/refresh**
```json
{
  "refresh_token": "your-refresh-token"
}
```

**POST /api/v1/auth/logout**
Requires authentication header.

### Domain Endpoints

**GET /api/v1/domains** - List all domains
**POST /api/v1/domains** - Create a new domain
**GET /api/v1/domains/:id** - Get domain details
**PUT /api/v1/domains/:id** - Update domain
**DELETE /api/v1/domains/:id** - Delete domain

### DNS Endpoints

**GET /api/v1/dns/zones** - List DNS zones
**GET /api/v1/dns/zones/:id/records** - Get DNS records for zone
**POST /api/v1/dns/zones/:id/records** - Create DNS record
**PUT /api/v1/dns/records/:id** - Update DNS record
**DELETE /api/v1/dns/records/:id** - Delete DNS record

### Database Endpoints

**GET /api/v1/databases** - List databases
**POST /api/v1/databases** - Create database
**GET /api/v1/databases/:id** - Get database details
**DELETE /api/v1/databases/:id** - Delete database
**POST /api/v1/databases/:id/users** - Create database user

### Email Endpoints

**GET /api/v1/email/accounts** - List email accounts
**POST /api/v1/email/accounts** - Create email account
**DELETE /api/v1/email/accounts/:id** - Delete email account
**GET /api/v1/email/forwarders** - List email forwarders
**POST /api/v1/email/forwarders** - Create email forwarder

### SSL Endpoints

**GET /api/v1/ssl** - List SSL certificates
**POST /api/v1/ssl/letsencrypt** - Install Let's Encrypt certificate
**POST /api/v1/ssl/install** - Install custom certificate
**DELETE /api/v1/ssl/:id** - Delete certificate

## Security

### Best Practices

1. **Change default credentials immediately**
2. **Use strong JWT secrets** (at least 32 characters)
3. **Enable 2FA** for all admin accounts
4. **Keep PostgreSQL and Redis** on private networks
5. **Use HTTPS** in production
6. **Regular backups** - Configure automated backups
7. **Update regularly** - Keep all dependencies up to date
8. **Monitor logs** - Review audit logs regularly

### Security Features

- JWT with short-lived access tokens (15 min)
- Refresh token rotation
- Password hashing with bcrypt (cost factor: 12)
- Rate limiting on all endpoints
- IP-based firewall rules
- Two-factor authentication (TOTP)
- Comprehensive audit logging
- Session management with Redis
- Input validation on all endpoints
- SQL injection prevention
- XSS protection
- CSRF protection

## Resource Quotas

Default quotas per user:
- **Disk Space:** 10 GB
- **Bandwidth:** 100 GB/month
- **Domains:** 10
- **Databases:** 10
- **Email Accounts:** 50
- **FTP Accounts:** 10
- **Cron Jobs:** 10

Quotas can be customized per user by administrators.

## Monitoring

Access real-time monitoring:
- CPU usage
- Memory usage
- Disk I/O
- Network bandwidth
- Service status (Web server, Database, Redis, etc.)
- Resource usage graphs
- Alert system for threshold violations

## Backup & Restore

### Automated Backups

Configure backup schedule in settings:
```bash
# Default: Daily at 2 AM
0 2 * * *
```

### Manual Backup

```bash
POST /api/v1/backups/create
{
  "type": "full",
  "includes": ["databases", "files", "emails"]
}
```

### Restore

```bash
POST /api/v1/backups/:id/restore
{
  "databases": true,
  "files": true,
  "emails": false
}
```

## Troubleshooting

### Database Connection Issues

```bash
# Check PostgreSQL is running
docker-compose ps postgres

# View PostgreSQL logs
docker-compose logs postgres

# Restart PostgreSQL
docker-compose restart postgres
```

### Redis Connection Issues

```bash
# Check Redis is running
docker-compose ps redis

# Test Redis connection
docker-compose exec redis redis-cli ping
```

### Backend Issues

```bash
# View backend logs
docker-compose logs backend

# Restart backend
docker-compose restart backend

# Check for migration issues
docker-compose exec backend npm run migrate
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and questions:
- Create an issue on GitHub
- Email: support@surfacepanel.com
- Documentation: https://docs.surfacepanel.com

## Roadmap

### Phase 1 (Current)
- ✅ Core authentication system
- ✅ User management
- ✅ Domain management
- ✅ DNS management
- ✅ Database management
- ✅ Email management
- ✅ SSL/TLS management
- ✅ File management
- ✅ Backup system
- ✅ Monitoring

### Phase 2 (Planned)
- Multi-server support
- Kubernetes integration
- Advanced analytics
- Mobile app (React Native)
- Plugin/extension system
- Marketplace
- Advanced billing
- Support ticket system

### Phase 3 (Future)
- Machine learning for security
- Blockchain audit logs
- GraphQL API
- Real-time collaboration
- White-label options

## Acknowledgments

- Inspired by cPanel, DirectAdmin, and Plesk
- Built with modern web technologies
- Community-driven development

---

**SurfacePanel** - Modern Web Hosting Control Panel
