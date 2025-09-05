# Sanctum CRM - First Boot Configuration Plan

## 📋 Overview

This document outlines the comprehensive plan to transform Sanctum CRM from a hardcoded application into a fully configurable system with a first boot setup mode. The goal is to make the CRM easily deployable across different environments (Linux LEMP, LAMP, Windows PHP) with proper configuration management.

## 🎯 Objectives

1. **First Boot Setup Mode**: Detect first installation and guide users through initial configuration
2. **Company Name Configuration**: Simple company name setup for application branding
3. **Environment Detection**: Automatic detection of server environment and requirements
4. **Configuration Management**: Centralized, database-driven configuration system
5. **Deployment Guides**: Comprehensive setup instructions for different server environments
6. **Settings Management**: Admin interface for ongoing configuration changes

## 🔍 Current State Analysis

### Hardcoded Configuration Issues
- **Application Identity**: `APP_NAME` hardcoded to "Sanctum CRM"
- **Default Admin**: Hardcoded admin user with fixed credentials
- **Company Name**: No configurable company branding
- **Security Settings**: Static session and security configurations

### Current Configuration Structure
```php
// Current hardcoded values in config.php
define('APP_NAME', 'Sanctum CRM');
define('APP_URL', 'https://sanctum-crm.local');
// Company name and other settings will be database-driven
```

## 🏗️ Proposed Architecture

### 1. Configuration System Redesign

#### A. Configuration Storage
- **Database Table**: `system_config` for runtime configuration
- **Environment File**: `.env` for sensitive data (API keys, passwords)
- **Config Class**: `ConfigManager` for centralized configuration access
- **Fallback System**: Default values when configuration is missing

#### B. Configuration Categories
1. **Application Settings**
   - Company name
   - Application URL, version
   - Timezone settings

2. **Database Settings**
   - Backup settings
   - Database maintenance

3. **Security Settings**
   - Session configuration
   - API rate limits
   - Password policies

4. **Server Environment**
   - Web server type (Apache, Nginx)
   - PHP version and extensions
   - File permissions

### 2. First Boot Detection System

#### A. Installation State Detection
```php
class InstallationManager {
    public function isFirstBoot(): bool {
        // Check if database exists and has system_config table
        // Check if admin user exists
        // Check if company information is configured
    }
    
    public function getInstallationStep(): string {
        // Return current step: 'database', 'company', 'admin', 'complete'
    }
}
```

#### B. First Boot Flow
1. **Environment Check**: Verify PHP version, extensions, permissions
2. **Database Setup**: Create tables and initial data (database path is fixed for security)
3. **Company Configuration**: Set company name
4. **Admin Account**: Create initial administrator account
5. **Final Setup**: Complete installation and redirect to dashboard

### 3. Configuration Management Interface

#### A. Settings Page Enhancement
- **Company Settings Tab**: Company name configuration
- **System Settings Tab**: Application configuration
- **Security Settings Tab**: Security policies
- **Server Info Tab**: Environment information

#### B. Configuration API
- RESTful API endpoints for configuration management
- Validation for all configuration changes
- Audit logging for configuration modifications

## 📝 Implementation Plan

### Phase 1: Database Schema Setup

#### A. New Tables
```sql
-- System configuration table
CREATE TABLE system_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category VARCHAR(50) NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    data_type VARCHAR(20) DEFAULT 'string',
    is_encrypted BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(category, config_key)
);

-- Company information table
CREATE TABLE company_info (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name VARCHAR(255) NOT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Installation state table
CREATE TABLE installation_state (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    step VARCHAR(50) NOT NULL,
    is_completed BOOLEAN DEFAULT 0,
    completed_at DATETIME,
    data TEXT, -- JSON data for step-specific information
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### B. Database Initialization
- Create all required tables on first run
- Insert default configuration data
- No migration system needed for fresh installation

### Phase 2: Configuration Management Classes

#### A. ConfigManager Class
```php
class ConfigManager {
    private $db;
    private $cache = [];
    
    public function get($category, $key, $default = null);
    public function set($category, $key, $value, $encrypt = false);
    public function getCategory($category);
    public function setCategory($category, $configs);
    public function delete($category, $key);
    public function clearCache();
}
```

#### B. InstallationManager Class
```php
class InstallationManager {
    public function isFirstBoot(): bool;
    public function getCurrentStep(): string;
    public function completeStep($step, $data = null);
    public function getInstallationProgress(): array;
    public function validateStep($step, $data): array;
}
```

### Phase 3: First Boot UI Components

#### A. Installation Wizard Pages
1. **Welcome Page**: Introduction and requirements check
2. **Environment Check**: PHP version, extensions, permissions
3. **Database Setup**: SQLite database initialization (path fixed for security)
4. **Company Information**: Company name setup
5. **Admin Account**: Create initial administrator
6. **Final Setup**: Complete installation

#### B. UI Components
- **Progress Indicator**: Show installation progress
- **Validation Messages**: Real-time form validation
- **Environment Checker**: System requirements validation
- **Configuration Forms**: Dynamic forms based on configuration schema

### Phase 4: Settings Management Enhancement

#### A. Enhanced Settings Page
- **Tabbed Interface**: Organized configuration sections
- **Real-time Validation**: Immediate feedback on configuration changes
- **Import/Export**: Configuration backup and restore
- **Reset Options**: Reset to defaults or previous configuration

#### B. Configuration Schema
```php
$configSchema = [
    'application' => [
        'app_name' => ['type' => 'string', 'required' => true],
        'app_url' => ['type' => 'url', 'required' => true],
        'timezone' => ['type' => 'timezone', 'required' => true]
    ],
    'company' => [
        'company_name' => ['type' => 'string', 'required' => true]
    ],
    'database' => [
        'backup_enabled' => ['type' => 'boolean', 'required' => false]
    ]
];
```

### Phase 5: Environment-Specific Configuration

#### A. Environment Detection
```php
class EnvironmentDetector {
    public function detectWebServer(): string;
    public function detectPHPVersion(): string;
    public function detectExtensions(): array;
    public function detectOS(): string;
    public function getRecommendedConfig(): array;
}
```

#### B. Server-Specific Optimizations
- **Apache**: .htaccess configuration templates
- **Nginx**: Server block configuration templates
- **Windows**: IIS configuration templates
- **Linux**: Systemd service files

### Phase 6: Deployment Guides

#### A. Linux LEMP Stack
```bash
# Ubuntu/Debian LEMP Setup
sudo apt update && sudo apt upgrade -y
sudo apt install nginx mysql-server php8.1-fpm php8.1-mysql php8.1-sqlite3 php8.1-curl php8.1-mbstring php8.1-openssl php8.1-json php8.1-zip php8.1-xml php8.1-gd

# Nginx configuration
# MySQL setup
# PHP-FPM configuration
# SSL certificate setup
```

#### B. Linux LAMP Stack
```bash
# Ubuntu/Debian LAMP Setup
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 mysql-server php8.1 php8.1-mysql php8.1-sqlite3 php8.1-curl php8.1-mbstring php8.1-openssl php8.1-json php8.1-zip php8.1-xml php8.1-gd

# Apache configuration
# MySQL setup
# SSL certificate setup
```

#### C. Windows PHP Server
```powershell
# Windows setup with XAMPP/WAMP
# IIS configuration
# PHP configuration
# Database setup
```

## 🔧 Technical Implementation Details

### 1. Configuration Loading Order
1. **Default Values**: Hardcoded fallbacks in config.php
2. **Environment Variables**: .env file values
3. **Database Configuration**: Runtime configuration from database
4. **User Overrides**: Admin-configured values

### 2. Security Considerations
- **Encrypted Storage**: Sensitive data encrypted in database
- **Input Validation**: All configuration inputs validated
- **Access Control**: Configuration changes require admin privileges
- **Audit Logging**: Track all configuration changes
- **Fixed Database Path**: Database path is hardcoded for security - no user configuration to prevent path traversal attacks

### 3. Performance Optimizations
- **Configuration Caching**: Cache frequently accessed configurations
- **Lazy Loading**: Load configurations only when needed
- **Database Indexing**: Optimize configuration queries

### 4. Fresh Installation Strategy
- **Clean Installation**: Fresh database setup with all required tables
- **Default Configuration**: Pre-populated with sensible defaults
- **No Legacy Support**: No need to support existing installations

## 📊 Configuration Categories Detail

### Application Settings
- Company name
- Application URL and version
- Timezone settings

### Database Settings
- Backup configuration
- Database maintenance

### Security Settings
- Session configuration
- Password policies
- API rate limiting
- Security headers

### Server Settings
- Web server configuration
- PHP settings
- File permissions
- Logging configuration

## 🚀 Deployment Scenarios

### Scenario 1: Fresh Installation
1. User downloads Sanctum CRM
2. Uploads to web server
3. Visits application URL
4. First boot wizard guides through setup
5. Application ready for use

### Scenario 2: Code Update
1. User has existing Sanctum CRM installation
2. Updates to new version
3. Database tables created if they don't exist
4. Configuration system initialized
5. New configuration options available

### Scenario 3: Development Environment
1. Developer clones repository
2. Runs local development server
3. First boot wizard with development defaults
4. Easy configuration for local development

## 📋 Testing Strategy

### Unit Tests
- Configuration management functions
- Installation manager logic
- Environment detection
- Validation functions

### Integration Tests
- First boot wizard flow
- Configuration API endpoints
- Database initialization
- Settings management interface

### End-to-End Tests
- Complete installation process
- Configuration changes
- Environment-specific deployments
- Database initialization scenarios

## 📈 Success Metrics

### User Experience
- Installation completion rate
- Time to first successful login
- Configuration error rate
- User satisfaction scores

### Technical Metrics
- Configuration load time
- Database query performance
- Memory usage optimization
- Error rates and debugging

## 🔄 Future Enhancements

### Advanced Features
- **Multi-tenant Configuration**: Support for multiple companies
- **Configuration Templates**: Pre-built configuration sets
- **Auto-update System**: Automatic configuration updates
- **Configuration Analytics**: Usage and performance analytics

### Integration Options
- **Docker Support**: Containerized deployment
- **Kubernetes**: Orchestrated deployment
- **CI/CD Integration**: Automated deployment pipelines
- **Monitoring Integration**: Health checks and monitoring

## 📚 Documentation Requirements

### User Documentation
- Installation guides for each environment
- Configuration management guide
- Troubleshooting documentation
- Video tutorials

### Developer Documentation
- API documentation for configuration
- Extension development guide
- Custom configuration guide
- Contributing guidelines

### Administrator Documentation
- System administration guide
- Security configuration guide
- Performance tuning guide
- Backup and recovery procedures

---

## 🎯 Next Steps

1. **Review and Approve Plan**: Stakeholder review of this comprehensive plan
2. **Phase 1 Implementation**: Begin with database schema updates
3. **Prototype Development**: Create proof-of-concept for first boot wizard
4. **User Testing**: Test with different environments and user scenarios
5. **Iterative Development**: Implement phases incrementally with feedback

This plan provides a solid foundation for transforming Sanctum CRM into a fully configurable, enterprise-ready application that can be easily deployed across different environments while maintaining security and performance standards.
