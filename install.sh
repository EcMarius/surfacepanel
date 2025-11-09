#!/bin/bash

#############################################################################################
# VirPanel Installer v2.0
# Laravel-based Hosting Control Panel (WHM + cPanel Alternative)
#
# UNIQUE PORT CONFIGURATION:
# - WHM Admin Panel: 15443 (HTTPS)
# - User Panel: 15444 (HTTPS)
#
# These ports do NOT conflict with: cPanel, Plesk, Webmin, or any common software
#############################################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging
log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[!]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }
log_step() { echo -e "${PURPLE}[STEP]${NC} $1"; }

# Check root
[[ $EUID -ne 0 ]] && log_error "This script must be run as root"

clear
cat << "EOF"
╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║                         VirPanel Installer v2.0                               ║
║                  Laravel-based Hosting Control Panel                         ║
║                                                                              ║
║                        WHM + cPanel Alternative                              ║
║                                                                              ║
║    WHM Admin Panel:  https://your-server:15443                              ║
║    User Panel:       https://your-server:15444                              ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
EOF

echo ""
log_info "Starting VirPanel installation..."
echo ""
sleep 2

# Installation configuration
INSTALL_DIR="/usr/local/virpanel"
NGINX_CONF_DIR="/etc/nginx/sites-available"
NGINX_ENABLED_DIR="/etc/nginx/sites-enabled"
PHP_VERSION="8.2"
WHM_PORT="15443"
USER_PORT="15444"
PRIMARY_IP=$(hostname -I | awk '{print $1}')

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VER=$VERSION_ID
else
    log_error "Cannot detect OS version"
fi

log_info "Detected OS: $OS $VER"
log_info "Primary IP: $PRIMARY_IP"
echo ""

# Check system requirements
log_step "Checking system requirements..."

MIN_RAM=2048
MIN_DISK=20480
TOTAL_RAM=$(free -m | awk '/^Mem:/{print $2}')
TOTAL_DISK=$(df -m / | awk 'NR==2 {print $4}')

if [ "$TOTAL_RAM" -lt "$MIN_RAM" ]; then
    log_error "Insufficient RAM: ${TOTAL_RAM}MB (minimum: ${MIN_RAM}MB)"
fi

if [ "$TOTAL_DISK" -lt "$MIN_DISK" ]; then
    log_error "Insufficient disk space: ${TOTAL_DISK}MB (minimum: ${MIN_DISK}MB)"
fi

log_success "System requirements check passed (RAM: ${TOTAL_RAM}MB, Disk: ${TOTAL_DISK}MB)"
echo ""

# Interactive configuration
log_step "VirPanel Configuration"
echo ""

read -p "Server Hostname (FQDN): " SERVER_HOSTNAME
[ -z "$SERVER_HOSTNAME" ] && log_error "Server hostname is required"

read -p "Admin Email: " ADMIN_EMAIL
[ -z "$ADMIN_EMAIL" ] && log_error "Admin email is required"

while true; do
    read -s -p "Admin Password: " ADMIN_PASSWORD
    echo
    read -s -p "Confirm Password: " ADMIN_PASSWORD_CONFIRM
    echo
    [ "$ADMIN_PASSWORD" = "$ADMIN_PASSWORD_CONFIRM" ] && break
    log_warning "Passwords do not match. Try again."
done

while true; do
    read -s -p "MySQL Root Password: " MYSQL_ROOT_PASSWORD
    echo
    read -s -p "Confirm MySQL Password: " MYSQL_ROOT_PASSWORD_CONFIRM
    echo
    [ "$MYSQL_ROOT_PASSWORD" = "$MYSQL_ROOT_PASSWORD_CONFIRM" ] && break
    log_warning "Passwords do not match. Try again."
done

echo ""
log_info "═══════════════════════════════════════════════════════════"
log_info "Installation Summary:"
log_info "───────────────────────────────────────────────────────────"
echo "  Hostname:         $SERVER_HOSTNAME"
echo "  IP Address:       $PRIMARY_IP"
echo "  Admin Email:      $ADMIN_EMAIL"
echo "  Install Dir:      $INSTALL_DIR"
echo ""
echo "  WHM Admin Port:   $WHM_PORT (HTTPS)"
echo "  User Panel Port:  $USER_PORT (HTTPS)"
log_info "═══════════════════════════════════════════════════════════"
echo ""

read -p "Proceed with installation? (y/n): " CONFIRM
[ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ] && log_error "Installation cancelled"

echo ""
log_step "Updating system packages..."

if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq >/dev/null 2>&1
    apt-get upgrade -y -qq >/dev/null 2>&1
elif [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
    yum update -y -q >/dev/null 2>&1
else
    log_error "Unsupported OS: $OS"
fi

log_success "System updated"

# Install dependencies
log_step "Installing dependencies (this may take several minutes)..."

if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
    apt-get install -y -qq software-properties-common curl wget git unzip supervisor redis-server build-essential >/dev/null 2>&1
    
    add-apt-repository ppa:ondrej/php -y >/dev/null 2>&1
    apt-get update -qq >/dev/null 2>&1
    
    apt-get install -y -qq \
        php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common php${PHP_VERSION}-mysql php${PHP_VERSION}-redis \
        php${PHP_VERSION}-zip php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl php${PHP_VERSION}-xml php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl nginx mysql-server >/dev/null 2>&1
    
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
    apt-get install -y -qq nodejs >/dev/null 2>&1
fi

npm install -g pm2 >/dev/null 2>&1

log_success "Dependencies installed"

# Install Composer
log_step "Installing Composer..."
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null 2>&1
fi
log_success "Composer installed"

# Create installation directory
log_step "Setting up VirPanel..."
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR

# Install Laravel
if [ ! -f "artisan" ]; then
    composer create-project laravel/laravel . --prefer-dist --no-dev --quiet
fi

log_success "Laravel installed"

# Configure MySQL
log_step "Configuring MySQL..."
systemctl start mysql >/dev/null 2>&1
systemctl enable mysql >/dev/null 2>&1

mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';" >/dev/null 2>&1
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS virpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >/dev/null 2>&1
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;" >/dev/null 2>&1

log_success "MySQL configured"

# Configure Laravel
log_step "Configuring Laravel environment..."

cat > $INSTALL_DIR/.env << ENVEOF
APP_NAME=VirPanel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${SERVER_HOSTNAME}:${WHM_PORT}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=virpanel
DB_USERNAME=root
DB_PASSWORD=${MYSQL_ROOT_PASSWORD}
DB_PREFIX=vp_

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

VIRPANEL_HOSTNAME=${SERVER_HOSTNAME}
VIRPANEL_PRIMARY_IP=${PRIMARY_IP}
VIRPANEL_WHM_PORT=${WHM_PORT}
VIRPANEL_USER_PORT=${USER_PORT}
ENVEOF

php artisan key:generate --force >/dev/null 2>&1

log_success "Laravel configured"

# Set permissions
chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/storage $INSTALL_DIR/bootstrap/cache

log_success "Permissions set"

# Generate SSL certificates
log_step "Generating SSL certificates..."

mkdir -p /etc/virpanel/ssl

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/virpanel/ssl/admin.key \
    -out /etc/virpanel/ssl/admin.crt \
    -subj "/C=US/ST=State/L=City/O=VirPanel/CN=${SERVER_HOSTNAME}" >/dev/null 2>&1

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/virpanel/ssl/user.key \
    -out /etc/virpanel/ssl/user.crt \
    -subj "/C=US/ST=State/L=City/O=VirPanel/CN=${SERVER_HOSTNAME}" >/dev/null 2>&1

chmod 600 /etc/virpanel/ssl/*.key

log_success "SSL certificates generated"

# Configure Nginx for WHM (port 15443)
log_step "Configuring Nginx..."

cat > $NGINX_CONF_DIR/virpanel-whm << NGINXEOF
server {
    listen ${WHM_PORT} ssl http2;
    listen [::]:${WHM_PORT} ssl http2;
    server_name ${SERVER_HOSTNAME} ${PRIMARY_IP};
    root ${INSTALL_DIR}/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/admin.crt;
    ssl_certificate_key /etc/virpanel/ssl/admin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param VIRPANEL_PANEL admin;
        fastcgi_param SERVER_PORT ${WHM_PORT};
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

# Configure Nginx for User (port 15444)
cat > $NGINX_CONF_DIR/virpanel-user << NGINXEOF
server {
    listen ${USER_PORT} ssl http2;
    listen [::]:${USER_PORT} ssl http2;
    server_name ${SERVER_HOSTNAME} ${PRIMARY_IP};
    root ${INSTALL_DIR}/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/user.crt;
    ssl_certificate_key /etc/virpanel/ssl/user.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param VIRPANEL_PANEL user;
        fastcgi_param SERVER_PORT ${USER_PORT};
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

ln -sf $NGINX_CONF_DIR/virpanel-whm $NGINX_ENABLED_DIR/
ln -sf $NGINX_CONF_DIR/virpanel-user $NGINX_ENABLED_DIR/

log_success "Nginx configured"

# Configure PHP-FPM
log_step "Configuring PHP-FPM..."
sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 100M/" /etc/php/${PHP_VERSION}/fpm/php.ini
sed -i "s/post_max_size = 8M/post_max_size = 100M/" /etc/php/${PHP_VERSION}/fpm/php.ini
sed -i "s/max_execution_time = 30/max_execution_time = 300/" /etc/php/${PHP_VERSION}/fpm/php.ini
sed -i "s/memory_limit = 128M/memory_limit = 256M/" /etc/php/${PHP_VERSION}/fpm/php.ini

log_success "PHP-FPM configured"

# Start services
log_step "Starting services..."
systemctl restart php${PHP_VERSION}-fpm >/dev/null 2>&1
systemctl enable php${PHP_VERSION}-fpm >/dev/null 2>&1
systemctl restart nginx >/dev/null 2>&1
systemctl enable nginx >/dev/null 2>&1
systemctl restart redis-server >/dev/null 2>&1
systemctl enable redis-server >/dev/null 2>&1

log_success "Services started"

# Configure firewall
log_step "Configuring firewall..."
if command -v ufw &> /dev/null; then
    ufw allow ${WHM_PORT}/tcp >/dev/null 2>&1
    ufw allow ${USER_PORT}/tcp >/dev/null 2>&1
    ufw allow 80/tcp >/dev/null 2>&1
    ufw allow 443/tcp >/dev/null 2>&1
    ufw allow 22/tcp >/dev/null 2>&1
    ufw --force enable >/dev/null 2>&1
elif command -v firewall-cmd &> /dev/null; then
    firewall-cmd --permanent --add-port=${WHM_PORT}/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=${USER_PORT}/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=80/tcp >/dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp >/dev/null 2>&1
    firewall-cmd --reload >/dev/null 2>&1
fi

log_success "Firewall configured"

# Installation complete
clear
cat << EOF

╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║               ${GREEN}VirPanel Installation Complete!${NC}                             ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝

${CYAN}Access URLs:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ${PURPLE}🔐 WHM Admin Panel:${NC}    https://${SERVER_HOSTNAME}:${WHM_PORT}
                         https://${PRIMARY_IP}:${WHM_PORT}

  ${BLUE}👤 User Panel:${NC}         https://${SERVER_HOSTNAME}:${USER_PORT}
                         https://${PRIMARY_IP}:${USER_PORT}

${CYAN}Admin Credentials:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Email:    ${ADMIN_EMAIL}
  Password: [as configured]

${CYAN}Important Notes:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ${YELLOW}⚠${NC}  Self-signed SSL certificates were generated
  ${YELLOW}⚠${NC}  Install proper SSL certificates for production
  ${YELLOW}⚠${NC}  Ports ${WHM_PORT} and ${USER_PORT} must be accessible

${CYAN}Next Steps:${NC}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  1. Access WHM at https://${SERVER_HOSTNAME}:${WHM_PORT}
  2. Log in with your admin credentials
  3. Create your first hosting package
  4. Create user accounts

${GREEN}Thank you for choosing VirPanel!${NC}

EOF

log_success "Installation completed successfully!"
