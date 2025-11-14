# SurfacePanel Architecture

## Overview
SurfacePanel is a modern, scalable web hosting control panel alternative to cPanel, built with security, performance, and user experience in mind.

## Technology Stack

### Backend
- **Runtime**: Node.js 20.x LTS
- **Framework**: Express.js 4.x
- **Language**: TypeScript 5.x
- **API**: RESTful with versioning (v1)
- **Authentication**: JWT with refresh tokens, 2FA support
- **Validation**: Joi for request validation
- **ORM**: Knex.js with PostgreSQL

### Frontend
- **Framework**: React 18.x
- **Language**: TypeScript 5.x
- **State Management**: Redux Toolkit
- **UI Components**: Material-UI (MUI)
- **Routing**: React Router v6
- **API Client**: Axios with interceptors
- **Forms**: React Hook Form with Yup validation

### Database
- **Primary DB**: PostgreSQL 15.x
- **Caching**: Redis 7.x
- **Session Store**: Redis
- **Search**: PostgreSQL Full-Text Search

### Infrastructure
- **Containerization**: Docker & Docker Compose
- **Web Servers**: Nginx (reverse proxy), Apache/Nginx (user sites)
- **Process Manager**: PM2
- **Task Queue**: Bull (Redis-based)
- **Logging**: Winston + Morgan
- **Monitoring**: Prometheus + Grafana (optional)

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Client (Browser)                     │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Nginx Reverse Proxy                       │
│                  (SSL Termination, Load Balancing)           │
└────────────┬────────────────────────────┬────────────────────┘
             │                            │
             ▼                            ▼
┌────────────────────────┐    ┌──────────────────────────────┐
│   React Frontend       │    │   Express Backend API        │
│   (Static Assets)      │    │   (Node.js/TypeScript)       │
└────────────────────────┘    └──────────┬───────────────────┘
                                         │
                    ┌────────────────────┼────────────────────┐
                    │                    │                    │
                    ▼                    ▼                    ▼
        ┌───────────────────┐ ┌──────────────────┐ ┌─────────────────┐
        │   PostgreSQL      │ │      Redis       │ │  File System    │
        │   (Main DB)       │ │ (Cache/Sessions) │ │  (User Files)   │
        └───────────────────┘ └──────────────────┘ └─────────────────┘
                    │
                    ▼
        ┌───────────────────────────────────────────────────────┐
        │              System Services Layer                    │
        │  - DNS (BIND)      - Email (Postfix/Dovecot)         │
        │  - Web Servers     - FTP (vsftpd)                     │
        │  - MySQL/MariaDB   - Firewall (iptables/UFW)         │
        │  - SSL (Certbot)   - Backup (rsync/tar)              │
        └───────────────────────────────────────────────────────┘
```

## Core Modules

### 1. Authentication & Authorization
- JWT-based authentication with access and refresh tokens
- Role-based access control (RBAC): Admin, Reseller, User
- Two-factor authentication (TOTP)
- Password policies and encryption (bcrypt)
- Session management with Redis
- API key management for automation

### 2. User Management
- Multi-tenant architecture
- User profiles and preferences
- Account suspension/activation
- Resource quota management
- Activity logging and audit trails

### 3. Domain Management
- Domain registration API integration
- DNS zone management (BIND integration)
- Subdomain creation and management
- Domain aliases and redirects
- Domain validation and verification
- WHOIS privacy

### 4. File Management
- Web-based file manager (upload, download, edit)
- FTP/SFTP account management (vsftpd integration)
- File permissions and ownership (chmod/chown)
- Disk quota enforcement
- File compression/extraction
- Trash/recycle bin
- File versioning

### 5. Database Management
- MySQL/MariaDB database creation and management
- PostgreSQL database management
- Database user creation with privilege management
- phpMyAdmin/Adminer integration
- Database backup and restore
- Import/export (SQL, CSV)
- Query execution limits

### 6. Email Management
- Email account creation (Postfix/Dovecot)
- Email forwarders and aliases
- Catch-all addresses
- Mailing lists
- Spam filtering (SpamAssassin)
- Email quota management
- Webmail integration (Roundcube/Rainloop)
- DKIM, SPF, DMARC configuration
- Email routing and MX records

### 7. SSL/TLS Management
- Let's Encrypt integration with auto-renewal
- Custom SSL certificate upload
- CSR generation
- Certificate validation and monitoring
- Force HTTPS options
- HSTS configuration
- SSL certificate store

### 8. DNS Management
- DNS zone editor (A, AAAA, CNAME, MX, TXT, SRV, CAA)
- DNS templates for quick setup
- DNSSEC support
- DNS propagation checking
- Zone file import/export
- Secondary DNS support

### 9. Backup & Restore
- Automated backup scheduling (cron-based)
- Full and incremental backups
- Selective restore (files, databases, emails)
- Remote backup storage (S3, FTP, SFTP)
- Backup encryption
- Retention policies
- Backup integrity verification

### 10. Security
- Web Application Firewall (ModSecurity)
- IP-based firewall rules (iptables/UFW)
- IP whitelisting/blacklisting
- Rate limiting and DDoS protection
- Malware scanning (ClamAV)
- Security audit logs
- Intrusion detection (Fail2Ban)
- Security headers (CSP, X-Frame-Options)
- Brute force protection

### 11. Resource Monitoring
- Real-time CPU usage tracking
- Memory usage monitoring
- Disk I/O monitoring
- Network bandwidth tracking
- Service status monitoring (systemd integration)
- Resource usage graphs and charts
- Alert system for threshold violations
- Historical data and trends

### 12. Web Server Management
- Apache/Nginx configuration
- Virtual host management
- PHP version management (PHP-FPM)
- PHP configuration per domain
- .htaccess editor
- Error log viewer
- Access log analysis
- HTTP/2 and HTTP/3 support

### 13. Cron Job Management
- Cron job creation and editing
- Visual cron expression builder
- Job execution history
- Output logging
- Email notifications
- Resource limits per job

### 14. Application Management
- One-click installers (WordPress, Joomla, etc.)
- Application version management
- Auto-updates
- Staging environments
- Git integration

## Security Considerations

### Authentication
- Passwords hashed with bcrypt (cost factor: 12)
- JWT tokens with short expiration (15 min access, 7 days refresh)
- Token rotation and revocation
- 2FA required for admin accounts
- Account lockout after failed attempts

### Authorization
- Principle of least privilege
- Resource-level permissions
- Action-based access control
- API rate limiting per user/IP

### Data Protection
- Input validation on all endpoints
- SQL injection prevention (parameterized queries)
- XSS protection (sanitization, CSP headers)
- CSRF protection (tokens)
- Secure headers (HSTS, X-Content-Type-Options)
- Data encryption at rest and in transit

### Audit & Compliance
- Comprehensive audit logging
- User action tracking
- Change history
- Compliance reports (GDPR, HIPAA ready)

## Database Schema

### Core Tables
- `users` - User accounts and authentication
- `roles` - Role definitions
- `permissions` - Permission definitions
- `user_roles` - User-role assignments
- `sessions` - Active sessions
- `audit_logs` - System audit trail

### Resource Tables
- `domains` - Domain records
- `dns_zones` - DNS zone data
- `dns_records` - DNS records
- `databases` - Database instances
- `database_users` - Database user accounts
- `email_accounts` - Email accounts
- `email_forwarders` - Email forwarding rules
- `ssl_certificates` - SSL certificate store
- `ftp_accounts` - FTP/SFTP accounts
- `cron_jobs` - Scheduled tasks
- `backups` - Backup metadata

### System Tables
- `settings` - System configuration
- `quotas` - Resource quotas
- `usage_stats` - Resource usage tracking
- `firewall_rules` - Firewall configuration
- `api_keys` - API authentication keys

## API Design

### Versioning
- URL-based versioning: `/api/v1/`
- Backward compatibility guaranteed within major version

### Response Format
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully",
  "timestamp": "2025-11-14T10:30:00Z"
}
```

### Error Format
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": [
      {
        "field": "email",
        "message": "Invalid email format"
      }
    ]
  },
  "timestamp": "2025-11-14T10:30:00Z"
}
```

### Pagination
```json
{
  "data": [ ... ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}
```

## Error Handling

### Error Categories
1. **Validation Errors** (400): Invalid input data
2. **Authentication Errors** (401): Missing/invalid credentials
3. **Authorization Errors** (403): Insufficient permissions
4. **Not Found Errors** (404): Resource doesn't exist
5. **Conflict Errors** (409): Resource already exists
6. **Rate Limit Errors** (429): Too many requests
7. **Server Errors** (500): Internal server error
8. **Service Unavailable** (503): External service down

### Edge Cases Handled
- Concurrent modifications (optimistic locking)
- Resource cleanup on failures (transactions)
- Partial failures in batch operations
- Disk space exhaustion
- Service unavailability
- Invalid DNS configurations
- Certificate expiration
- Database connection pool exhaustion
- File permission errors
- Quota exceeded scenarios

## Deployment

### Development
```bash
docker-compose -f docker-compose.dev.yml up
```

### Production
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Environment Variables
- `NODE_ENV`: Environment (development/production)
- `DATABASE_URL`: PostgreSQL connection string
- `REDIS_URL`: Redis connection string
- `JWT_SECRET`: JWT signing secret
- `JWT_REFRESH_SECRET`: Refresh token secret
- `SMTP_*`: Email configuration
- `AWS_*`: S3 backup configuration (optional)

## Scalability

### Horizontal Scaling
- Stateless API servers (session in Redis)
- Load balancing with Nginx
- Database read replicas
- Redis clustering for high availability

### Performance Optimization
- Response caching (Redis)
- Database query optimization
- Connection pooling
- Asset minification and compression
- CDN for static assets
- Lazy loading and code splitting (frontend)

## Monitoring & Logging

### Logs
- Application logs (Winston)
- Access logs (Morgan)
- Error logs with stack traces
- Audit logs (user actions)
- System logs (service status)

### Metrics
- API response times
- Database query performance
- Error rates
- User activity metrics
- Resource utilization

## Backup Strategy

### What to Backup
- PostgreSQL database (daily)
- User files (daily incremental, weekly full)
- Configuration files (on change)
- SSL certificates (on renewal)
- Application code (git)

### Retention
- Daily backups: 7 days
- Weekly backups: 4 weeks
- Monthly backups: 12 months

## Disaster Recovery

### Recovery Time Objective (RTO)
- Critical services: < 1 hour
- Full system: < 4 hours

### Recovery Point Objective (RPO)
- Database: < 24 hours
- Files: < 24 hours

### Procedures
1. Restore database from backup
2. Restore user files
3. Reconfigure services
4. Verify system integrity
5. Update DNS if needed

## Development Workflow

### Code Organization
```
surfacepanel/
├── backend/              # Express API
│   ├── src/
│   │   ├── controllers/  # Request handlers
│   │   ├── models/       # Database models
│   │   ├── routes/       # API routes
│   │   ├── middleware/   # Express middleware
│   │   ├── services/     # Business logic
│   │   ├── utils/        # Utilities
│   │   ├── validators/   # Input validation
│   │   └── config/       # Configuration
│   ├── migrations/       # Database migrations
│   └── tests/            # Unit & integration tests
├── frontend/             # React app
│   ├── src/
│   │   ├── components/   # React components
│   │   ├── pages/        # Page components
│   │   ├── services/     # API clients
│   │   ├── store/        # Redux store
│   │   ├── hooks/        # Custom hooks
│   │   ├── utils/        # Utilities
│   │   └── types/        # TypeScript types
│   └── public/           # Static assets
├── docker/               # Docker configurations
├── docs/                 # Documentation
└── scripts/              # Utility scripts
```

### Testing Strategy
- Unit tests: 80%+ coverage
- Integration tests: Critical paths
- E2E tests: User workflows
- Security tests: OWASP Top 10
- Performance tests: Load testing

## Future Enhancements

### Phase 2
- Multi-server support
- Kubernetes integration
- Real-time collaboration
- Mobile app (React Native)
- Advanced analytics
- Machine learning for security threats
- Blockchain for audit logs
- GraphQL API option

### Phase 3
- Plugin/extension system
- Marketplace for themes/plugins
- White-label options
- Advanced billing with multiple payment gateways
- Support ticket system integration
- Live chat support
- Video tutorials integration

## License
MIT License (or choose appropriate license)
