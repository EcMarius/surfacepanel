#!/bin/bash

###############################################################################
# VirPanel Installation Script
#
# This script installs VirPanel on your server
# Supports: CentOS 7+, Ubuntu 20.04+, Debian 10+
#
# Usage: bash install.sh [--license=KEY] [--auto]
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/usr/local/virpanel"
DATA_DIR="/var/virpanel"
LOG_FILE="/var/log/virpanel-install.log"
LICENSE_SERVER_URL="https://license.virpanel.com/api/v1"
DOWNLOAD_URL="${LICENSE_SERVER_URL}/download"

# Functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Parse arguments
LICENSE_KEY=""
AUTO_INSTALL=false

for arg in "$@"; do
    case $arg in
        --license=*)
            LICENSE_KEY="${arg#*=}"
            ;;
        --auto)
            AUTO_INSTALL=true
            ;;
        --help)
            echo "VirPanel Installation Script"
            echo ""
            echo "Usage: bash install.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --license=KEY    Provide license key"
            echo "  --auto           Run installation without prompts"
            echo "  --help           Show this help message"
            echo ""
            exit 0
            ;;
    esac
done

# Banner
clear
cat << "EOF"
 __      ___      ____                  _
 \ \    / (_)    |  _ \                | |
  \ \  / / _ _ __| |_) | __ _ _ __   ___| |
   \ \/ / | | '__|  _ < / _` | '_ \ / _ \ |
    \  /  | | |  | |_) | (_| | | | |  __/ |
     \/   |_|_|  |____/ \__,_|_| |_|\___|_|

     Modern Web Hosting Control Panel
     Version 0.1.0

EOF

log "Starting VirPanel installation..."

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   error "This script must be run as root (use sudo)"
fi

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VERSION=$VERSION_ID
        log "Detected OS: $OS $VERSION"
    else
        error "Cannot detect operating system"
    fi
}

# Check system requirements
check_requirements() {
    log "Checking system requirements..."

    # Check RAM
    TOTAL_RAM=$(free -m | awk '/^Mem:/{print $2}')

    if [ "$TOTAL_RAM" -lt 512 ]; then
        error "Minimum 512MB RAM required (detected: ${TOTAL_RAM}MB)"
    elif [ "$TOTAL_RAM" -lt 2048 ]; then
        warning "VPS installation detected (${TOTAL_RAM}MB RAM). Some features may be limited."
        INSTALL_TYPE="vps"
    else
        log "Standalone server detected (${TOTAL_RAM}MB RAM)"
        INSTALL_TYPE="standalone"
    fi

    # Check disk space (minimum 10GB)
    AVAILABLE_SPACE=$(df -BG / | awk 'NR==2 {print $4}' | sed 's/G//')

    if [ "$AVAILABLE_SPACE" -lt 10 ]; then
        error "Minimum 10GB free disk space required (available: ${AVAILABLE_SPACE}GB)"
    fi

    success "System requirements met"
}

# Check if files exist, download if needed
check_files() {
    log "Checking installation files..."

    if [ ! -f "composer.json" ] || [ ! -f "package.json" ]; then
        log "Installation files not found. Downloading from license server..."

        if [ -z "$LICENSE_KEY" ]; then
            if [ "$AUTO_INSTALL" = false ]; then
                read -p "Enter your license key: " LICENSE_KEY
            else
                error "License key required for download (use --license=KEY)"
            fi
        fi

        log "Downloading VirPanel from license server..."

        # Download package
        TEMP_FILE=$(mktemp)

        curl -f -L "${DOWNLOAD_URL}?license=${LICENSE_KEY}" -o "$TEMP_FILE" || \
            error "Failed to download from license server. Please check your license key."

        log "Extracting files..."

        # Extract
        mkdir -p "$INSTALL_DIR"
        tar -xzf "$TEMP_FILE" -C "$INSTALL_DIR" || \
            error "Failed to extract installation files"

        rm -f "$TEMP_FILE"

        # Change to install directory
        cd "$INSTALL_DIR"

        success "Files downloaded successfully"
    else
        log "Installation files found"
    fi
}

# Install dependencies based on OS
install_dependencies() {
    log "Installing system dependencies..."

    case $OS in
        ubuntu|debian)
            export DEBIAN_FRONTEND=noninteractive

            apt-get update
            apt-get install -y \
                php8.2 \
                php8.2-cli \
                php8.2-fpm \
                php8.2-mysql \
                php8.2-pgsql \
                php8.2-mbstring \
                php8.2-xml \
                php8.2-curl \
                php8.2-zip \
                php8.2-gd \
                php8.2-redis \
                mysql-server \
                redis-server \
                nginx \
                curl \
                wget \
                git \
                unzip \
                supervisor \
                certbot \
                python3-certbot-nginx
            ;;

        centos|rhel|rocky|almalinux)
            yum install -y epel-release
            yum install -y \
                php82 \
                php82-cli \
                php82-fpm \
                php82-mysqlnd \
                php82-pgsql \
                php82-mbstring \
                php82-xml \
                php82-curl \
                php82-zip \
                php82-gd \
                php82-redis \
                mariadb-server \
                redis \
                nginx \
                curl \
                wget \
                git \
                unzip \
                supervisor \
                certbot \
                python3-certbot-nginx
            ;;

        *)
            error "Unsupported operating system: $OS"
            ;;
    esac

    success "System dependencies installed"
}

# Install Composer
install_composer() {
    log "Installing Composer..."

    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
    fi

    success "Composer installed"
}

# Install Node.js and npm
install_nodejs() {
    log "Installing Node.js..."

    if ! command -v node &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_18.x | bash -

        case $OS in
            ubuntu|debian)
                apt-get install -y nodejs
                ;;
            centos|rhel|rocky|almalinux)
                yum install -y nodejs
                ;;
        esac
    fi

    success "Node.js installed"
}

# Install PHP dependencies
install_php_deps() {
    log "Installing PHP dependencies..."

    cd "$INSTALL_DIR"
    composer install --no-dev --optimize-autoloader

    success "PHP dependencies installed"
}

# Install frontend dependencies
install_frontend_deps() {
    log "Installing frontend dependencies..."

    cd "$INSTALL_DIR"
    npm install
    npm run build

    success "Frontend dependencies installed"
}

# Setup database
setup_database() {
    log "Setting up database..."

    # Start MySQL
    systemctl start mysql || systemctl start mariadb
    systemctl enable mysql || systemctl enable mariadb

    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 32)

    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS virpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS 'virpanel'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -e "GRANT ALL PRIVILEGES ON virpanel.* TO 'virpanel'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"

    # Save credentials
    cat > "$INSTALL_DIR/.env" << EOL
APP_NAME=VirPanel
APP_ENV=production
APP_DEBUG=false
APP_URL=https://$(hostname -f)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=virpanel
DB_USERNAME=virpanel
DB_PASSWORD=${DB_PASSWORD}
DB_PREFIX=vp_

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

LICENSE_KEY=${LICENSE_KEY}
LICENSE_SERVER=${LICENSE_SERVER_URL}

MAIL_DRIVER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
EOL

    chmod 600 "$INSTALL_DIR/.env"

    success "Database configured"
}

# Run migrations
run_migrations() {
    log "Running database migrations..."

    cd "$INSTALL_DIR"
    php scripts/migrate.php --seed

    success "Database migrations completed"
}

# Configure web server
configure_nginx() {
    log "Configuring Nginx..."

    cat > /etc/nginx/sites-available/virpanel << 'EOL'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /usr/local/virpanel/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOL

    ln -sf /etc/nginx/sites-available/virpanel /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default

    nginx -t
    systemctl restart nginx
    systemctl enable nginx

    success "Nginx configured"
}

# Setup directories
setup_directories() {
    log "Creating directories..."

    mkdir -p "$DATA_DIR"/{backups,logs,cache,sessions,uploads}
    mkdir -p "$INSTALL_DIR"/storage/{cache/views,logs}

    chown -R www-data:www-data "$INSTALL_DIR"
    chown -R www-data:www-data "$DATA_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 755 "$DATA_DIR"

    success "Directories created"
}

# Setup firewall
setup_firewall() {
    log "Configuring firewall..."

    if command -v ufw &> /dev/null; then
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw allow 2087/tcp  # WHM port
        ufw allow 2083/tcp  # cPanel port
        ufw --force enable
    elif command -v firewall-cmd &> /dev/null; then
        firewall-cmd --permanent --add-service=ssh
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-port=2087/tcp
        firewall-cmd --permanent --add-port=2083/tcp
        firewall-cmd --reload
    fi

    success "Firewall configured"
}

# Main installation
main() {
    detect_os
    check_requirements
    check_files
    install_dependencies
    install_composer
    install_nodejs
    install_php_deps
    install_frontend_deps
    setup_directories
    setup_database
    run_migrations
    configure_nginx
    setup_firewall

    # Final output
    clear
    cat << EOF

${GREEN}╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║     VirPanel Installation Completed Successfully!        ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝${NC}

Access Information:
-------------------
Panel URL:      ${BLUE}http://$(hostname -f)${NC}
WHM URL:        ${BLUE}http://$(hostname -f):2087${NC}
cPanel URL:     ${BLUE}http://$(hostname -f):2083${NC}

Default Credentials:
-------------------
Username:       ${GREEN}root${NC}
Password:       ${GREEN}virpanel${NC}

${YELLOW}IMPORTANT: Change the default password immediately after login!${NC}

Next Steps:
-----------
1. Access the WHM panel and change the root password
2. Configure your license in Settings > License
3. Set up nameservers in Settings > Nameservers
4. Configure email settings in Settings > Email
5. Create your first hosting package
6. Create your first account using: ${BLUE}createacct${NC}

Documentation:
--------------
Full documentation: https://docs.virpanel.com

Support:
--------
Email: support@virpanel.com
Forum: https://forum.virpanel.com

Installation log saved to: $LOG_FILE

EOF
}

# Run installation
main
