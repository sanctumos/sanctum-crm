# FreeOpsDAO CRM - Deployment Guide

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Development Environment](#development-environment)
3. [Production Deployment](#production-deployment)
4. [Server Configuration](#server-configuration)
5. [Database Setup](#database-setup)
6. [Security Configuration](#security-configuration)
7. [SSL/HTTPS Setup](#sslhttps-setup)
8. [Backup Strategy](#backup-strategy)
9. [Monitoring & Logging](#monitoring--logging)
10. [Performance Optimization](#performance-optimization)
11. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

### System Requirements
- **Operating System**: Linux (Ubuntu 20.04+ recommended), Windows Server, or macOS
- **PHP**: 8.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: SQLite 3 (included with PHP)
- **Memory**: Minimum 512MB RAM, 1GB+ recommended
- **Storage**: 10GB+ available space
- **Network**: Stable internet connection for webhooks

### PHP Extensions Required
```bash
# Check installed extensions
php -m

# Required extensions
- sqlite3
- json
- curl
- mbstring
- openssl
- session
- pdo
- pdo_sqlite
```

### Install PHP Extensions (Ubuntu/Debian)
```bash
sudo apt update
sudo apt install php8.0-sqlite3 php8.0-curl php8.0-mbstring php8.0-openssl php8.0-json
```

### Install PHP Extensions (CentOS/RHEL)
```bash
sudo yum install php-sqlite3 php-curl php-mbstring php-openssl php-json
```

---

## 🛠 Development Environment

### Quick Start (Local Development)
```bash
# 1. Clone repository
git clone https://github.com/actuallyrizzn/crm.freeopsdao.com.git
cd crm.freeopsdao.com

# 2. Set permissions
chmod 755 -R public/
chmod 644 db/crm.db 2>/dev/null || true
chmod 755 db/

# 3. Start development server
cd public
php -S localhost:8000

# 4. Access application
# Open browser to http://localhost:8000
# Default admin: admin/admin123
```

### Development with Docker
```dockerfile
# Dockerfile
FROM php:8.0-apache

# Install extensions
RUN docker-php-ext-install sqlite3 pdo pdo_sqlite

# Copy application
COPY . /var/www/html/
WORKDIR /var/www/html/public

# Set permissions
RUN chown -R www-data:www-data /var/www/html/db
RUN chmod 755 -R /var/www/html/public
RUN chmod 644 /var/www/html/db/crm.db 2>/dev/null || true

# Apache configuration
RUN a2enmod rewrite
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
```

```yaml
# docker-compose.yml
version: '3.8'
services:
  crm:
    build: .
    ports:
      - "8000:80"
    volumes:
      - ./db:/var/www/html/db
      - ./public:/var/www/html/public
    environment:
      - APP_URL=http://localhost:8000
      - DEBUG_MODE=true
```

### Development Configuration
```php
// public/includes/config.php (Development)
define('APP_URL', 'http://localhost:8000');
define('DEBUG_MODE', true);
define('SESSION_LIFETIME', 3600);
define('API_RATE_LIMIT', 1000);
```

---

## 🚀 Production Deployment

### 1. Server Preparation

#### Update System
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

#### Install Required Software
```bash
# Ubuntu/Debian
sudo apt install apache2 php8.0 php8.0-sqlite3 php8.0-curl php8.0-mbstring php8.0-openssl php8.0-json git unzip

# CentOS/RHEL
sudo yum install httpd php php-sqlite3 php-curl php-mbstring php-openssl php-json git unzip
```

### 2. Application Deployment

#### Clone Application
```bash
# Create application directory
sudo mkdir -p /var/www/crm
sudo chown $USER:$USER /var/www/crm

# Clone repository
cd /var/www/crm
git clone https://github.com/actuallyrizzn/crm.freeopsdao.com.git .

# Set proper ownership
sudo chown -R www-data:www-data /var/www/crm
```

#### Set Permissions
```bash
# Set directory permissions
sudo chmod 755 -R /var/www/crm/public
sudo chmod 755 /var/www/crm/db
sudo chmod 644 /var/www/crm/db/crm.db 2>/dev/null || true

# Ensure web server can write to database
sudo chown www-data:www-data /var/www/crm/db
sudo chmod 775 /var/www/crm/db
```

### 3. Production Configuration
```php
// public/includes/config.php (Production)
define('APP_NAME', 'FreeOpsDAO CRM');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://your-domain.com');
define('DEBUG_MODE', false);

// Database Configuration
define('DB_PATH', '/var/www/crm/db/crm.db');
define('DB_BACKUP_PATH', '/var/www/crm/db/backup/');

// Security Configuration
define('SESSION_NAME', 'crm_session');
define('SESSION_LIFETIME', 3600);
define('API_KEY_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 1000);
define('API_MAX_PAYLOAD_SIZE', 1048576);

// Error Reporting
error_reporting(0);
ini_set('display_errors', 0);

// Timezone
date_default_timezone_set('UTC');

// Session Security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
```

---

## 🌐 Server Configuration

### Apache Configuration

#### Virtual Host Configuration
```apache
# /etc/apache2/sites-available/crm.conf
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/crm/public
    
    # Redirect HTTP to HTTPS
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/crm/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/your-domain.crt
    SSLCertificateKeyFile /etc/ssl/private/your-domain.key
    SSLCertificateChainFile /etc/ssl/certs/your-domain-chain.crt
    
    # Security Headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Application Configuration
    <Directory /var/www/crm/public>
        AllowOverride All
        Require all granted
        
        # PHP Configuration
        php_value upload_max_filesize 10M
        php_value post_max_size 10M
        php_value max_execution_time 300
        php_value memory_limit 256M
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/crm_error.log
    CustomLog ${APACHE_LOG_DIR}/crm_access.log combined
    
    # Gzip Compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/plain
        AddOutputFilterByType DEFLATE text/html
        AddOutputFilterByType DEFLATE text/xml
        AddOutputFilterByType DEFLATE text/css
        AddOutputFilterByType DEFLATE application/xml
        AddOutputFilterByType DEFLATE application/xhtml+xml
        AddOutputFilterByType DEFLATE application/rss+xml
        AddOutputFilterByType DEFLATE application/javascript
        AddOutputFilterByType DEFLATE application/x-javascript
        AddOutputFilterByType DEFLATE application/json
    </IfModule>
</VirtualHost>
```

#### Enable Site
```bash
# Enable required modules
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod rewrite
sudo a2enmod deflate

# Enable site
sudo a2ensite crm.conf

# Disable default site
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

### Nginx Configuration

#### Server Block Configuration
```nginx
# /etc/nginx/sites-available/crm
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/your-domain.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.key;
    ssl_certificate_chain /etc/ssl/certs/your-domain-chain.crt;
    
    # SSL Security
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    
    # Document Root
    root /var/www/crm/public;
    index index.php;
    
    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300;
    }
    
    # Application Routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # API Routes
    location /api/ {
        try_files $uri $uri/ /api/v1/index.php?$query_string;
    }
    
    # Static Files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Security - Block access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(includes|db|tests|docs)/ {
        deny all;
    }
    
    # Logs
    access_log /var/log/nginx/crm_access.log;
    error_log /var/log/nginx/crm_error.log;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
}
```

#### Enable Site
```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

---

## 🗄 Database Setup

### Initial Database Creation
```bash
# The database will be created automatically on first access
# But you can pre-create it for better control

# Create database directory
sudo mkdir -p /var/www/crm/db
sudo chown www-data:www-data /var/www/crm/db
sudo chmod 775 /var/www/crm/db

# Create database file
sudo -u www-data sqlite3 /var/www/crm/db/crm.db ".databases"

# Set permissions
sudo chmod 664 /var/www/crm/db/crm.db
```

### Database Backup Setup
```bash
# Create backup directory
sudo mkdir -p /var/www/crm/db/backup
sudo chown www-data:www-data /var/www/crm/db/backup
sudo chmod 775 /var/www/crm/db/backup

# Create backup script
sudo nano /var/www/crm/backup.sh
```

```bash
#!/bin/bash
# /var/www/crm/backup.sh

BACKUP_DIR="/var/www/crm/db/backup"
DB_PATH="/var/www/crm/db/crm.db"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup
sqlite3 "$DB_PATH" ".backup '$BACKUP_DIR/crm_backup_$DATE.db'"

# Compress backup
gzip "$BACKUP_DIR/crm_backup_$DATE.db"

# Remove old backups
find "$BACKUP_DIR" -name "crm_backup_*.db.gz" -mtime +$RETENTION_DAYS -delete

# Log backup
echo "$(date): Database backup completed - crm_backup_$DATE.db.gz" >> /var/log/crm_backup.log
```

```bash
# Make script executable
sudo chmod +x /var/www/crm/backup.sh

# Add to crontab for daily backups
sudo crontab -e

# Add this line for daily backups at 2 AM
0 2 * * * /var/www/crm/backup.sh
```

### Database Maintenance
```bash
# Optimize database (run weekly)
sudo -u www-data sqlite3 /var/www/crm/db/crm.db "VACUUM;"

# Check database integrity
sudo -u www-data sqlite3 /var/www/crm/db/crm.db "PRAGMA integrity_check;"

# Analyze database
sudo -u www-data sqlite3 /var/www/crm/db/crm.db "ANALYZE;"
```

---

## 🔒 Security Configuration

### File Permissions
```bash
# Set secure permissions
sudo find /var/www/crm -type d -exec chmod 755 {} \;
sudo find /var/www/crm -type f -exec chmod 644 {} \;

# Special permissions for database
sudo chmod 664 /var/www/crm/db/crm.db
sudo chmod 775 /var/www/crm/db

# Ensure web server ownership
sudo chown -R www-data:www-data /var/www/crm
```

### PHP Security Configuration
```ini
; /etc/php/8.0/apache2/php.ini
; Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
file_uploads = On
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.cookie_samesite = Lax

; Error handling
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

### Firewall Configuration
```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# iptables (CentOS)
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT
sudo iptables -A INPUT -j DROP
sudo service iptables save
```

### Fail2ban Configuration
```ini
# /etc/fail2ban/jail.local
[crm-auth]
enabled = true
port = http,https
filter = crm-auth
logpath = /var/log/apache2/crm_access.log
maxretry = 3
bantime = 3600
findtime = 600
```

```ini
# /etc/fail2ban/filter.d/crm-auth.conf
[Definition]
failregex = ^<HOST>.*POST.*login\.php.* 403
ignoreregex =
```

```bash
# Enable fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## 🔐 SSL/HTTPS Setup

### Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Self-Signed Certificate (Development)
```bash
# Generate private key
sudo openssl genrsa -out /etc/ssl/private/your-domain.key 2048

# Generate certificate
sudo openssl req -new -x509 -key /etc/ssl/private/your-domain.key \
  -out /etc/ssl/certs/your-domain.crt -days 365 \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=your-domain.com"
```

### SSL Configuration
```apache
# Apache SSL configuration
SSLEngine on
SSLCertificateFile /etc/ssl/certs/your-domain.crt
SSLCertificateKeyFile /etc/ssl/private/your-domain.key
SSLCertificateChainFile /etc/ssl/certs/your-domain-chain.crt

# SSL Security
SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
SSLCipherSuite ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
SSLHonorCipherOrder on
SSLCompression off
```

---

## 💾 Backup Strategy

### Automated Backup Script
```bash
#!/bin/bash
# /var/www/crm/backup_full.sh

BACKUP_DIR="/var/backups/crm"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Database backup
sqlite3 /var/www/crm/db/crm.db ".backup '$BACKUP_DIR/crm_db_$DATE.db'"
gzip "$BACKUP_DIR/crm_db_$DATE.db"

# Application backup
tar -czf "$BACKUP_DIR/crm_app_$DATE.tar.gz" -C /var/www/crm .

# Upload to cloud storage (optional)
# aws s3 cp "$BACKUP_DIR/crm_db_$DATE.db.gz" s3://your-bucket/crm/
# aws s3 cp "$BACKUP_DIR/crm_app_$DATE.tar.gz" s3://your-bucket/crm/

# Remove old backups
find "$BACKUP_DIR" -name "crm_*" -mtime +$RETENTION_DAYS -delete

# Log backup
echo "$(date): Full backup completed" >> /var/log/crm_backup.log
```

### Backup Monitoring
```bash
# Check backup status
#!/bin/bash
# /var/www/crm/check_backup.sh

BACKUP_DIR="/var/backups/crm"
LATEST_BACKUP=$(find "$BACKUP_DIR" -name "crm_db_*.db.gz" -printf '%T@ %p\n' | sort -n | tail -1 | cut -f2- -d" ")

if [ -n "$LATEST_BACKUP" ]; then
    BACKUP_AGE=$(( ($(date +%s) - $(stat -c %Y "$LATEST_BACKUP")) / 86400 ))
    
    if [ $BACKUP_AGE -gt 2 ]; then
        echo "WARNING: Backup is $BACKUP_AGE days old" | mail -s "CRM Backup Alert" admin@your-domain.com
    fi
else
    echo "ERROR: No backup found" | mail -s "CRM Backup Alert" admin@your-domain.com
fi
```

---

## 📊 Monitoring & Logging

### Log Configuration
```bash
# Create log directory
sudo mkdir -p /var/log/crm
sudo chown www-data:www-data /var/log/crm

# Application logs
sudo touch /var/log/crm/application.log
sudo touch /var/log/crm/api.log
sudo touch /var/log/crm/error.log
sudo chown www-data:www-data /var/log/crm/*.log
```

### Log Rotation
```bash
# /etc/logrotate.d/crm
/var/log/crm/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload apache2
    endscript
}
```

### System Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Create monitoring script
#!/bin/bash
# /var/www/crm/monitor.sh

# Check disk space
DISK_USAGE=$(df /var/www/crm | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "WARNING: Disk usage is ${DISK_USAGE}%" | mail -s "CRM Disk Alert" admin@your-domain.com
fi

# Check database size
DB_SIZE=$(du -m /var/www/crm/db/crm.db | cut -f1)
if [ $DB_SIZE -gt 1000 ]; then
    echo "WARNING: Database size is ${DB_SIZE}MB" | mail -s "CRM Database Alert" admin@your-domain.com
fi

# Check web server status
if ! systemctl is-active --quiet apache2; then
    echo "ERROR: Apache is not running" | mail -s "CRM Service Alert" admin@your-domain.com
fi
```

---

## ⚡ Performance Optimization

### PHP Optimization
```ini
; /etc/php/8.0/apache2/php.ini
; Performance settings
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.enable_cli = 1

; Session optimization
session.gc_maxlifetime = 3600
session.gc_probability = 1
session.gc_divisor = 100
```

### Apache Optimization
```apache
# /etc/apache2/mods-available/mpm_prefork.conf
<IfModule mpm_prefork_module>
    StartServers          5
    MinSpareServers       5
    MaxSpareServers      10
    MaxRequestWorkers    150
    MaxConnectionsPerChild   0
</IfModule>
```

### Database Optimization
```sql
-- Run periodically
VACUUM;
ANALYZE;
PRAGMA optimize;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email);
CREATE INDEX IF NOT EXISTS idx_contacts_type ON contacts(contact_type);
CREATE INDEX IF NOT EXISTS idx_deals_stage ON deals(stage);
CREATE INDEX IF NOT EXISTS idx_deals_contact ON deals(contact_id);
```

### Caching
```apache
# Apache caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/ico "access plus 1 year"
    ExpiresByType image/icon "access plus 1 year"
    ExpiresByType text/plain "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType application/x-shockwave-flash "access plus 1 month"
</IfModule>
```

---

## 🐛 Troubleshooting

### Common Issues

#### 1. Database Connection Issues
```bash
# Check SQLite extension
php -m | grep sqlite

# Check database permissions
ls -la /var/www/crm/db/crm.db
sudo chown www-data:www-data /var/www/crm/db/crm.db
sudo chmod 664 /var/www/crm/db/crm.db

# Check database integrity
sudo -u www-data sqlite3 /var/www/crm/db/crm.db "PRAGMA integrity_check;"
```

#### 2. Permission Issues
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/crm

# Fix permissions
sudo find /var/www/crm -type d -exec chmod 755 {} \;
sudo find /var/www/crm -type f -exec chmod 644 {} \;
sudo chmod 664 /var/www/crm/db/crm.db
sudo chmod 775 /var/www/crm/db
```

#### 3. Web Server Issues
```bash
# Check Apache status
sudo systemctl status apache2
sudo apache2ctl configtest

# Check Nginx status
sudo systemctl status nginx
sudo nginx -t

# Check error logs
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/nginx/error.log
```

#### 4. PHP Issues
```bash
# Check PHP version
php -v

# Check PHP configuration
php -i | grep "Loaded Configuration File"

# Check PHP error log
sudo tail -f /var/log/php_errors.log
```

### Debug Mode
```php
// Enable debug mode temporarily
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Health Check Script
```bash
#!/bin/bash
# /var/www/crm/health_check.sh

echo "=== CRM Health Check ==="
echo "Date: $(date)"
echo

# Check web server
echo "Web Server Status:"
if systemctl is-active --quiet apache2; then
    echo "✓ Apache is running"
else
    echo "✗ Apache is not running"
fi

# Check database
echo
echo "Database Status:"
if [ -f /var/www/crm/db/crm.db ]; then
    echo "✓ Database file exists"
    DB_SIZE=$(du -h /var/www/crm/db/crm.db | cut -f1)
    echo "  Size: $DB_SIZE"
else
    echo "✗ Database file missing"
fi

# Check disk space
echo
echo "Disk Space:"
DISK_USAGE=$(df /var/www/crm | tail -1 | awk '{print $5}')
echo "Usage: $DISK_USAGE"

# Check recent logs
echo
echo "Recent Errors:"
tail -5 /var/log/crm/error.log 2>/dev/null || echo "No error log found"

# Test API endpoint
echo
echo "API Test:"
curl -s -o /dev/null -w "%{http_code}" https://your-domain.com/api/v1/contacts || echo "Failed"
```

### Emergency Recovery
```bash
# Restore from backup
#!/bin/bash
# /var/www/crm/restore.sh

BACKUP_FILE="$1"
if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file>"
    exit 1
fi

# Stop web server
sudo systemctl stop apache2

# Restore database
gunzip -c "$BACKUP_FILE" | sqlite3 /var/www/crm/db/crm.db

# Fix permissions
sudo chown www-data:www-data /var/www/crm/db/crm.db
sudo chmod 664 /var/www/crm/db/crm.db

# Start web server
sudo systemctl start apache2

echo "Restore completed"
```

---

## 📋 Deployment Checklist

### Pre-Deployment
- [ ] Server requirements met
- [ ] PHP extensions installed
- [ ] Web server configured
- [ ] SSL certificate obtained
- [ ] Domain DNS configured
- [ ] Firewall configured

### Deployment
- [ ] Application cloned
- [ ] Permissions set correctly
- [ ] Configuration updated
- [ ] Database initialized
- [ ] Web server restarted
- [ ] SSL configured

### Post-Deployment
- [ ] Application accessible
- [ ] API endpoints working
- [ ] Admin login functional
- [ ] Backup system configured
- [ ] Monitoring setup
- [ ] Security audit completed

### Maintenance
- [ ] Regular backups running
- [ ] Log rotation configured
- [ ] Updates scheduled
- [ ] Performance monitoring active
- [ ] Security patches applied

---

**Deployment Guide Version**: 1.0.0  
**Last Updated**: 2025  
**Compatible with**: CRM System v1.0.0 