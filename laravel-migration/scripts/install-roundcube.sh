#!/bin/bash

###############################################################################
# Roundcube Webmail Installation Script for VirPanel
#
# Usage: install-roundcube.sh <install_path> <db_name> <db_user> <db_pass> <des_key>
#
# This script:
# - Downloads latest stable Roundcube
# - Extracts and configures it
# - Creates database and user
# - Sets up proper permissions
###############################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "This script must be run as root"
    exit 1
fi

# Check arguments
if [ "$#" -ne 5 ]; then
    print_error "Usage: $0 <install_path> <db_name> <db_user> <db_pass> <des_key>"
    exit 1
fi

INSTALL_PATH="$1"
DB_NAME="$2"
DB_USER="$3"
DB_PASS="$4"
DES_KEY="$5"

# Roundcube version to install
ROUNDCUBE_VERSION="1.6.5"
DOWNLOAD_URL="https://github.com/roundcube/roundcubemail/releases/download/${ROUNDCUBE_VERSION}/roundcubemail-${ROUNDCUBE_VERSION}-complete.tar.gz"

print_info "Starting Roundcube ${ROUNDCUBE_VERSION} installation..."

# Create installation directory if it doesn't exist
if [ ! -d "$INSTALL_PATH" ]; then
    print_info "Creating installation directory: $INSTALL_PATH"
    mkdir -p "$INSTALL_PATH"
fi

# Download Roundcube
print_info "Downloading Roundcube ${ROUNDCUBE_VERSION}..."
TMP_DIR=$(mktemp -d)
cd "$TMP_DIR"

if ! wget -q "$DOWNLOAD_URL" -O roundcube.tar.gz; then
    print_error "Failed to download Roundcube"
    rm -rf "$TMP_DIR"
    exit 1
fi

print_info "Download completed"

# Extract Roundcube
print_info "Extracting Roundcube..."
if ! tar -xzf roundcube.tar.gz; then
    print_error "Failed to extract Roundcube"
    rm -rf "$TMP_DIR"
    exit 1
fi

# Copy files to installation directory
print_info "Copying files to $INSTALL_PATH..."
ROUNDCUBE_DIR="roundcubemail-${ROUNDCUBE_VERSION}"
cp -r "${ROUNDCUBE_DIR}"/* "$INSTALL_PATH/"

# Clean up temp directory
rm -rf "$TMP_DIR"

# Create database
print_info "Creating database..."

# Get MySQL root credentials from Laravel .env or use defaults
if [ -f "/home/user/surfacepanel/laravel-migration/.env" ]; then
    DB_HOST=$(grep DB_HOST /home/user/surfacepanel/laravel-migration/.env | cut -d '=' -f2 || echo "localhost")
    DB_ROOT_USER=$(grep DB_USERNAME /home/user/surfacepanel/laravel-migration/.env | cut -d '=' -f2 || echo "root")
    DB_ROOT_PASS=$(grep DB_PASSWORD /home/user/surfacepanel/laravel-migration/.env | cut -d '=' -f2 || echo "")
else
    DB_HOST="localhost"
    DB_ROOT_USER="root"
    DB_ROOT_PASS=""
fi

# Create database and user
mysql -h"${DB_HOST}" -u"${DB_ROOT_USER}" -p"${DB_ROOT_PASS}" <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    print_info "Database created successfully"
else
    print_error "Failed to create database"
    exit 1
fi

# Import Roundcube database schema
print_info "Importing database schema..."
mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${INSTALL_PATH}/SQL/mysql.initial.sql"

if [ $? -eq 0 ]; then
    print_info "Database schema imported successfully"
else
    print_error "Failed to import database schema"
    exit 1
fi

# Create config directory if it doesn't exist
mkdir -p "${INSTALL_PATH}/config"

# Create temporary config file (will be overwritten by Laravel)
print_info "Creating initial configuration..."
cat > "${INSTALL_PATH}/config/config.inc.php" <<EOF
<?php

/* VirPanel Roundcube Configuration */
/* This is a temporary configuration - will be managed by VirPanel */

\$config['db_dsnw'] = 'mysql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}';
\$config['des_key'] = '${DES_KEY}';
\$config['product_name'] = 'VirPanel Webmail';
\$config['plugins'] = ['archive', 'zipdownload', 'managesieve'];
\$config['skin'] = 'elastic';
\$config['enable_installer'] = false;

EOF

# Set proper permissions
print_info "Setting permissions..."
chown -R www-data:www-data "$INSTALL_PATH"
chmod -R 755 "$INSTALL_PATH"
chmod -R 777 "${INSTALL_PATH}/temp"
chmod -R 777 "${INSTALL_PATH}/logs"

# Create nginx configuration
print_info "Creating Nginx configuration..."
cat > "/etc/nginx/conf.d/roundcube.conf" <<EOF
# Roundcube Webmail Configuration
location /webmail {
    alias ${INSTALL_PATH};
    index index.php;

    location ~ ^/webmail/(README|INSTALL|LICENSE|CHANGELOG|UPGRADING)$ {
        deny all;
    }

    location ~ ^/webmail/(bin|SQL|config|temp|logs)/ {
        deny all;
    }

    location ~ ^/webmail/(.+\.php)$ {
        alias ${INSTALL_PATH};
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$request_filename;
    }

    location ~* ^/webmail/(.+\.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt))$ {
        alias ${INSTALL_PATH};
        access_log off;
        expires 30d;
    }
}
EOF

# Reload Nginx
print_info "Reloading Nginx..."
if systemctl reload nginx; then
    print_info "Nginx reloaded successfully"
else
    print_warning "Failed to reload Nginx - please reload manually"
fi

# Create VirPanel SSO plugin directory
print_info "Setting up SSO integration..."
mkdir -p "${INSTALL_PATH}/plugins/virpanel_sso"

# Create SSO plugin
cat > "${INSTALL_PATH}/plugins/virpanel_sso/virpanel_sso.php" <<'EOFPLUGIN'
<?php

/**
 * VirPanel SSO Plugin for Roundcube
 *
 * Enables single sign-on from VirPanel user panel
 */

class virpanel_sso extends rcube_plugin
{
    public $task = 'login';

    function init()
    {
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('authenticate', array($this, 'authenticate'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();

        // Check for SSO token
        if (!empty($_GET['sso'])) {
            $token = $_GET['sso'];

            // Validate token with VirPanel API
            $response = $this->validate_sso_token($token);

            if ($response && $response['success']) {
                // Store credentials in session for auto-login
                $_SESSION['virpanel_email'] = $response['email'];
                $_SESSION['virpanel_password'] = $response['password'];
                $_SESSION['virpanel_sso'] = true;

                // Redirect to login
                header('Location: ./');
                exit;
            }
        }

        return $args;
    }

    function authenticate($args)
    {
        // Check if SSO session exists
        if (!empty($_SESSION['virpanel_sso'])) {
            $args['user'] = $_SESSION['virpanel_email'];
            $args['pass'] = $_SESSION['virpanel_password'];
            $args['valid'] = true;

            // Clear SSO flag after first use
            unset($_SESSION['virpanel_sso']);
        }

        return $args;
    }

    private function validate_sso_token($token)
    {
        // Call VirPanel API to validate token
        $api_url = 'http://localhost:15444/webmail/validate-token';

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        }

        return null;
    }
}
EOFPLUGIN

# Create plugin composer.json
cat > "${INSTALL_PATH}/plugins/virpanel_sso/composer.json" <<'EOF'
{
    "name": "virpanel/sso",
    "description": "VirPanel Single Sign-On integration",
    "type": "roundcube-plugin",
    "license": "MIT",
    "require": {
        "php": ">=7.0"
    }
}
EOF

print_info "SSO plugin installed"

# Final permissions check
chown -R www-data:www-data "$INSTALL_PATH"

print_info ""
print_info "========================================="
print_info "Roundcube installation completed!"
print_info "========================================="
print_info "Version: ${ROUNDCUBE_VERSION}"
print_info "Path: ${INSTALL_PATH}"
print_info "Database: ${DB_NAME}"
print_info "URL: http://your-domain/webmail"
print_info "========================================="
print_info ""
print_info "Next steps:"
print_info "1. Configure webmail settings in VirPanel admin panel"
print_info "2. Test webmail access from user panel"
print_info "3. Enable desired plugins"

exit 0
