# FreeOpsDAO CRM - Comprehensive Documentation

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Architecture & Technology Stack](#architecture--technology-stack)
3. [Installation & Setup](#installation--setup)
4. [Database Schema](#database-schema)
5. [API Reference](#api-reference)
6. [Web Interface](#web-interface)
7. [Authentication & Security](#authentication--security)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Integration Guide](#integration-guide)
11. [Troubleshooting](#troubleshooting)
12. [Development Guide](#development-guide)

---

## 🎯 System Overview

The FreeOpsDAO CRM is a modern Customer Relationship Management system designed for Web3 and traditional businesses. It features a unified contact management system, deal pipeline tracking, comprehensive reporting, and MCP (Message Control Plane) integration capabilities.

### Key Features
- **Unified Contact Management**: Combined leads and customers with Web3 integration
- **Deal Pipeline**: Kanban-style deal tracking with conversion analytics
- **User Management**: Admin interface with API key generation
- **Reports & Analytics**: Comprehensive sales analytics with export capabilities
- **Webhook System**: Real-time notifications and integrations
- **MCP-Ready API**: Machine-friendly endpoints for AI agents and automation
- **Modern UI**: Responsive Bootstrap 5 interface
- **Web3 Integration**: EVM addresses, social media handles, blockchain-ready

---

## 🏗 Architecture & Technology Stack

### Technology Stack
- **Backend**: PHP 8.0+
- **Frontend**: Bootstrap 5.x, JavaScript
- **Database**: SQLite 3
- **API**: RESTful with JSON responses
- **Authentication**: Session-based (web) + API keys (programmatic)

### Project Structure
```
crm.freeopsdao.com/
├── public/                  # Web root (all public files)
│   ├── index.php           # Main entry point
│   ├── login.php           # Authentication
│   ├── logout.php          # Session termination
│   ├── router.php          # URL routing
│   ├── api/                # API endpoints
│   │   ├── openapi.json    # API specification
│   │   └── v1/             # Version 1 API
│   │       ├── index.php   # Main API handler
│   │       ├── diagnostic.php
│   │       └── settings.php
│   ├── pages/              # Web interface pages
│   │   ├── dashboard.php   # Main dashboard
│   │   ├── contacts.php    # Contact management
│   │   ├── deals.php       # Deal pipeline
│   │   ├── users.php       # User management
│   │   ├── reports.php     # Analytics & reports
│   │   ├── settings.php    # User settings
│   │   ├── webhooks.php    # Webhook management
│   │   └── view_contact.php
│   ├── includes/           # PHP includes (private)
│   │   ├── config.php      # Configuration
│   │   ├── database.php    # Database handler
│   │   ├── auth.php        # Authentication
│   │   └── layout.php      # UI layout
│   └── assets/             # Static assets
│       ├── css/            # Stylesheets
│       └── js/             # JavaScript
├── db/                     # SQLite database (private)
├── tests/                  # Test suite (private)
│   ├── unit/              # Unit tests
│   ├── api/               # API tests
│   ├── integration/       # Integration tests
│   └── run_tests.php      # Test runner
├── docs/                  # Documentation
└── README.md              # Project overview
```

### Security Architecture
- **Web Root Isolation**: Only `/public` directory is web-accessible
- **Database Protection**: SQLite database stored outside web root
- **API Authentication**: Bearer token and query parameter authentication
- **Session Security**: HTTP-only cookies, secure session handling
- **Input Validation**: Comprehensive sanitization and validation

---

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.0 or higher
- SQLite3 extension enabled
- Web server (Apache/Nginx) or PHP built-in server
- Modern web browser

### Quick Start
```bash
# 1. Clone the repository
git clone https://github.com/actuallyrizzn/crm.freeopsdao.com.git
cd crm.freeopsdao.com

# 2. Set up development environment
cd public
php -S localhost:8000

# 3. Access the application
# Open browser to http://localhost:8000
# Default admin credentials: admin/admin123
```

### Production Setup
1. **Web Server Configuration**
   ```apache
   # Apache (.htaccess in public/)
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```

2. **Directory Permissions**
   ```bash
   chmod 755 -R public/
   chmod 644 db/crm.db
   chmod 755 db/
   ```

3. **Configuration**
   Edit `public/includes/config.php`:
   ```php
   define('APP_URL', 'https://your-domain.com');
   define('DEBUG_MODE', false);
   define('SESSION_LIFETIME', 3600);
   ```

---

## 🗄 Database Schema

### Core Tables

#### Users Table
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role VARCHAR(20) DEFAULT 'user',
    api_key VARCHAR(255) UNIQUE,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### Contacts Table (Unified Leads & Customers)
```sql
CREATE TABLE contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    position VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(50),
    -- Web3 & Social Media Fields
    evm_address VARCHAR(42),
    twitter_handle VARCHAR(50),
    linkedin_profile VARCHAR(255),
    telegram_username VARCHAR(50),
    discord_username VARCHAR(50),
    github_username VARCHAR(50),
    website VARCHAR(255),
    -- Contact Classification
    contact_type VARCHAR(10) DEFAULT 'lead',
    contact_status VARCHAR(20) DEFAULT 'new',
    source VARCHAR(50),
    assigned_to INTEGER,
    notes TEXT,
    -- Customer-specific fields
    first_purchase_date DATE,
    total_purchases DECIMAL(10,2) DEFAULT 0.00,
    last_purchase_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### Deals Table
```sql
CREATE TABLE deals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(200) NOT NULL,
    contact_id INTEGER NOT NULL,
    amount DECIMAL(10,2),
    stage VARCHAR(50) DEFAULT 'prospecting',
    probability INTEGER DEFAULT 0,
    expected_close_date DATE,
    assigned_to INTEGER,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id)
);
```

#### Webhooks Table
```sql
CREATE TABLE webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    url VARCHAR(255) NOT NULL,
    events TEXT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### API Requests Table
```sql
CREATE TABLE api_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id VARCHAR(64) UNIQUE,
    user_id INTEGER,
    endpoint VARCHAR(100),
    method VARCHAR(10),
    ip_address VARCHAR(45),
    user_agent TEXT,
    response_code INTEGER,
    response_time DECIMAL(10,3),
    status VARCHAR(20) DEFAULT 'pending',
    result TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 🔌 API Reference

### Base URL
```
https://your-domain.com/api/v1/
```

### Authentication
All API endpoints require authentication via API key:

#### Method 1: Bearer Token (Recommended)
```http
Authorization: Bearer YOUR_API_KEY
```

#### Method 2: Query Parameter
```
?api_key=YOUR_API_KEY
```

### Response Format
All responses are JSON with standard HTTP status codes:

#### Success Response
```json
{
  "id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com"
}
```

#### Error Response
```json
{
  "error": "Error description",
  "code": 400,
  "details": "Additional context (optional)"
}
```

### Endpoints

#### Contacts API
- `GET /contacts` - List all contacts
- `GET /contacts/{id}` - Get specific contact
- `GET /contacts?type=lead` - List leads only
- `GET /contacts?type=customer` - List customers only
- `POST /contacts` - Create new contact
- `PUT /contacts/{id}` - Update contact
- `PUT /contacts/{id}/convert` - Convert lead to customer
- `DELETE /contacts/{id}` - Delete contact

#### Deals API
- `GET /deals` - List all deals
- `GET /deals/{id}` - Get specific deal
- `POST /deals` - Create new deal
- `PUT /deals/{id}` - Update deal
- `DELETE /deals/{id}` - Delete deal

#### Users API
- `GET /users` - List all users (admin only)
- `GET /users/{id}` - Get specific user
- `POST /users` - Create new user (admin only)
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user (admin only)

#### Webhooks API
- `GET /webhooks` - List registered webhooks
- `POST /webhooks` - Register new webhook
- `PUT /webhooks/{id}` - Update webhook
- `DELETE /webhooks/{id}` - Remove webhook
- `POST /webhooks/{id}/test` - Test webhook delivery

#### Reports API
- `GET /reports/analytics` - Get analytics data
- `GET /reports/export` - Export data as CSV

### Example API Calls

#### Create Contact
```bash
curl -X POST https://your-domain.com/api/v1/contacts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "company": "Acme Corp",
    "evm_address": "0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b6",
    "twitter_handle": "@johndoe",
    "source": "website_form"
  }'
```

#### Convert Lead to Customer
```bash
curl -X PUT https://your-domain.com/api/v1/contacts/123/convert \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Create Deal
```bash
curl -X POST https://your-domain.com/api/v1/deals \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Enterprise License",
    "contact_id": 123,
    "amount": 5000.00,
    "stage": "proposal",
    "probability": 75,
    "expected_close_date": "2025-02-15"
  }'
```

---

## 🌐 Web Interface

### Pages Overview

#### Dashboard (`/pages/dashboard.php`)
- Key metrics and KPIs
- Recent activity feed
- Quick action buttons
- Sales pipeline overview

#### Contacts (`/pages/contacts.php`)
- Contact list with search and filters
- Add/edit contact forms
- Lead/customer conversion
- Contact assignment
- Export functionality

#### Deals (`/pages/deals.php`)
- Deal pipeline with Kanban view
- Deal creation and editing
- Stage progression tracking
- Deal assignment
- Amount and probability tracking

#### Users (`/pages/users.php`)
- User management (admin only)
- API key generation
- Role assignment
- User activation/deactivation

#### Reports (`/pages/reports.php`)
- Sales analytics
- Contact source breakdown
- Deal conversion rates
- Export capabilities
- Custom date ranges

#### Settings (`/pages/settings.php`)
- User profile management
- Password changes
- API key management
- Notification preferences

#### Webhooks (`/pages/webhooks.php`)
- Webhook registration
- Event configuration
- Delivery testing
- Log viewing

### UI Features
- **Responsive Design**: Mobile-friendly Bootstrap 5 interface
- **Interactive Components**: Modals, dropdowns, tooltips
- **Data Tables**: Sortable, searchable, paginated
- **Charts**: Chart.js integration for analytics
- **Real-time Updates**: AJAX-powered interactions

---

## 🔐 Authentication & Security

### Authentication Methods

#### Web Interface
- Session-based authentication
- Login/logout functionality
- Remember me option
- Password reset (planned)

#### API Access
- API key authentication
- Bearer token support
- Query parameter fallback
- Rate limiting

### Security Features
- **Password Hashing**: bcrypt with salt
- **Session Security**: HTTP-only cookies, secure flags
- **CSRF Protection**: Token-based protection
- **Input Validation**: Comprehensive sanitization
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output encoding
- **Rate Limiting**: API request throttling

### User Roles
- **Admin**: Full system access, user management
- **User**: Standard CRM access, own data management

### API Key Management
- Automatic generation on user creation
- Secure storage in database
- Regeneration capability
- Usage tracking and logging

---

## 🧪 Testing

### Test Structure
```
tests/
├── bootstrap.php           # Test environment setup
├── run_tests.php          # Main test runner
├── phpunit.xml            # PHPUnit configuration
├── unit/                  # Unit tests
│   ├── DatabaseTest.php   # Database operations
│   ├── AuthTest.php       # Authentication system
│   ├── ReportsTest.php    # Reporting functionality
│   ├── UserManagementTest.php # User management
│   └── WebhookTest.php    # Webhook system
├── api/                   # API integration tests
│   └── ApiTest.php        # API endpoints
├── integration/           # Integration tests
│   └── IntegrationTest.php # End-to-end workflows
└── README.md             # Test documentation
```

### Running Tests
```bash
# Run all tests
php tests/run_tests.php

# Run specific test suites
php tests/unit/DatabaseTest.php
php tests/unit/AuthTest.php
php tests/api/ApiTest.php

# Web interface
# Visit http://localhost:8000/tests/run_tests.php
```

### Test Coverage
- ✅ Database operations (CRUD, transactions)
- ✅ Authentication (login, API keys, roles)
- ✅ API endpoints (all major endpoints)
- ✅ Error handling (400, 401, 404, 500)
- ✅ Data validation (email, password, required fields)
- ✅ Business logic (lead conversion, deal stages)
- ✅ Security (authentication, authorization)

---

## 🚀 Deployment

### Development Environment
```bash
# Start PHP built-in server
cd public
php -S localhost:8000
```

### Production Deployment

#### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-crm-domain.com
    DocumentRoot /path/to/crm.freeopsdao.com/public
    
    <Directory /path/to/crm.freeopsdao.com/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-crm-domain.com;
    root /path/to/crm.freeopsdao.com/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Environment Configuration
```php
// Production settings in config.php
define('APP_URL', 'https://your-domain.com');
define('DEBUG_MODE', false);
define('SESSION_LIFETIME', 3600);
define('API_RATE_LIMIT', 1000);
```

### Database Backup
```bash
# Automated backup script
#!/bin/bash
BACKUP_DIR="/path/to/backups"
DB_PATH="/path/to/crm.freeopsdao.com/db/crm.db"
DATE=$(date +%Y%m%d_%H%M%S)

sqlite3 "$DB_PATH" ".backup '$BACKUP_DIR/crm_backup_$DATE.db'"
gzip "$BACKUP_DIR/crm_backup_$DATE.db"
```

---

## 🔗 Integration Guide

### Common Integration Scenarios

#### 1. Contact Form Integration
```javascript
const CRM_API_KEY = 'your_api_key_here';
const CRM_BASE_URL = 'https://your-domain.com/api/v1';

async function submitContact(formData) {
  const contactData = {
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    company: formData.get('company'),
    phone: formData.get('phone'),
    source: 'website_form'
  };
  
  try {
    const response = await fetch(`${CRM_BASE_URL}/contacts`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${CRM_API_KEY}`
      },
      body: JSON.stringify(contactData)
    });
    
    if (response.ok) {
      return await response.json();
    } else {
      throw new Error('Contact creation failed');
    }
  } catch (error) {
    console.error('CRM integration error:', error);
    throw error;
  }
}
```

#### 2. E-commerce Integration
```javascript
// Shopify webhook handler
app.post('/webhooks/customer/create', async (req, res) => {
  const customer = req.body;
  
  const crmData = {
    first_name: customer.first_name,
    last_name: customer.last_name,
    email: customer.email,
    phone: customer.phone,
    company: customer.company,
    contact_type: 'customer',
    contact_status: 'active',
    source: 'shopify_customer',
    notes: `Customer ID: ${customer.id}`
  };
  
  try {
    await fetch('https://your-domain.com/api/v1/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.CRM_API_KEY}`
      },
      body: JSON.stringify(crmData)
    });
  } catch (error) {
    console.error('CRM integration failed:', error);
  }
  
  res.status(200).send('OK');
});
```

#### 3. Newsletter Integration
```php
// WordPress hook example
add_action('wp_ajax_newsletter_signup', 'handle_newsletter_signup');

function handle_newsletter_signup() {
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    
    // Create CRM contact
    $crm_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'contact_type' => 'lead',
        'source' => 'newsletter_signup'
    );
    
    $crm_response = wp_remote_post('https://your-domain.com/api/v1/contacts', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . CRM_API_KEY
        ),
        'body' => json_encode($crm_data)
    ));
}
```

### Webhook Integration
```javascript
// Register webhook for contact creation
const webhookData = {
  url: 'https://your-app.com/webhooks/contact-created',
  events: 'contact.created,contact.updated'
};

await fetch('https://your-domain.com/api/v1/webhooks', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${CRM_API_KEY}`
  },
  body: JSON.stringify(webhookData)
});
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Database Connection Issues
```bash
# Check SQLite extension
php -m | grep sqlite

# Check database permissions
ls -la db/crm.db
chmod 644 db/crm.db
```

#### 2. API Authentication Failures
```bash
# Verify API key format
# Should be 32-character hex string
echo $API_KEY | wc -c

# Check API key in database
sqlite3 db/crm.db "SELECT username, api_key FROM users WHERE api_key IS NOT NULL;"
```

#### 3. File Permission Issues
```bash
# Set correct permissions
chmod 755 -R public/
chmod 644 db/crm.db
chmod 755 db/
chmod 755 tests/
```

#### 4. Session Issues
```php
// Check session configuration
ini_get('session.save_handler');
ini_get('session.save_path');
```

### Debug Mode
Enable debug mode in `config.php`:
```php
define('DEBUG_MODE', true);
```

### Log Files
- **API Debug**: `public/api/v1/debug.log`
- **PHP Errors**: Check web server error logs
- **Test Results**: `tests/test_results.json`

### Performance Issues
- **Database Optimization**: Add indexes for frequently queried fields
- **Caching**: Implement Redis/Memcached for session storage
- **CDN**: Use CDN for static assets
- **Database Backup**: Regular backup and maintenance

---

## 👨‍💻 Development Guide

### Development Environment Setup
```bash
# Clone repository
git clone https://github.com/actuallyrizzn/crm.freeopsdao.com.git
cd crm.freeopsdao.com

# Start development server
cd public
php -S localhost:8000

# Run tests
php tests/run_tests.php
```

### Code Structure

#### Database Layer (`includes/database.php`)
- Singleton pattern for database connection
- Prepared statements for security
- Transaction support
- Automatic table creation

#### Authentication (`includes/auth.php`)
- Session management
- API key validation
- Role-based access control
- Password hashing

#### Configuration (`includes/config.php`)
- Environment-specific settings
- Security configurations
- Helper functions
- Error handling

### Adding New Features

#### 1. Database Changes
```sql
-- Add new table
CREATE TABLE new_feature (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Add to database.php initializeTables()
```

#### 2. API Endpoints
```php
// Add to api/v1/index.php
case 'new_feature':
    handleNewFeature($method, $resourceId, $input, $auth);
    break;

function handleNewFeature($method, $id, $input, $auth) {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            // Implementation
            break;
        case 'POST':
            // Implementation
            break;
    }
}
```

#### 3. Web Interface
```php
// Create new page: pages/new_feature.php
<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Page implementation
?>
```

### Testing New Features
```php
// Add to appropriate test file
public function testNewFeature() {
    echo "  Testing new feature... ";
    
    try {
        // Test implementation
        echo "PASS\n";
    } catch (Exception $e) {
        echo "FAIL - " . $e->getMessage() . "\n";
    }
}
```

### Code Standards
- **PHP**: PSR-12 coding standards
- **JavaScript**: ES6+ with consistent formatting
- **HTML**: Semantic HTML5 with accessibility
- **CSS**: Bootstrap 5 with custom overrides
- **Database**: Consistent naming conventions
- **API**: RESTful design principles

### Git Workflow
```bash
# Feature development
git checkout -b feature/new-feature
# Make changes
git add .
git commit -m "Add new feature"
git push origin feature/new-feature

# Create pull request
# Code review
# Merge to main
```

---

## 📚 Additional Resources

### Documentation Files
- `README.md` - Project overview and quick start
- `docs/basic-crm-system.md` - System architecture
- `docs/api-integration-spec.md` - API specification
- `docs/phase-2-project-plan.md` - Development roadmap
- `docs/quick-integration-guide.md` - Integration examples

### External Resources
- [PHP Documentation](https://www.php.net/docs.php)
- [SQLite Documentation](https://www.sqlite.org/docs.html)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.0/)
- [REST API Design](https://restfulapi.net/)

### Support
- **GitHub Issues**: Report bugs and feature requests
- **Documentation**: Check `docs/` folder for detailed guides
- **Testing**: Run test suite to verify functionality
- **Community**: FreeOpsDAO development team

---

## 📄 License

This project is licensed under the CC-BY-SA License - see the [LICENSE](LICENSE) file for details.

---

**Version**: 1.0.0  
**Last Updated**: 2025  
**Maintainer**: FreeOpsDAO Development Team  
**Documentation Version**: Comprehensive v1.0 