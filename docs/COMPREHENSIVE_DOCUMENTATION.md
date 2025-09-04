# Best Jobs in TA - Comprehensive Documentation

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Installation & Deployment](#installation--deployment)
3. [API Reference](#api-reference)
4. [Integration Guide](#integration-guide)
5. [Security & Configuration](#security--configuration)
6. [Troubleshooting](#troubleshooting)
7. [Recent Updates & Fixes](#recent-updates--fixes)
8. [Development Guide](#development-guide)

---

## 🏗️ System Overview

### Technology Stack
- **Backend**: PHP 8.0+
- **Frontend**: Bootstrap 5.x, jQuery, Select2
- **Database**: SQLite 3
- **Server**: Apache/Nginx (or PHP built-in server for development)
- **Authentication**: Session-based with API key support

### Project Structure
```
bestjobsinta.com/
├── public/                  # Web root (all public files)
│   ├── index.php           # Main entry point
│   ├── login.php           # Authentication
│   ├── logout.php          # Session cleanup
│   ├── router.php          # Page routing
│   ├── api/
│   │   └── v1/
│   │       └── index.php   # API endpoints
│   ├── pages/              # Page templates
│   │   ├── dashboard.php
│   │   ├── contacts.php
│   │   ├── deals.php
│   │   ├── users.php
│   │   ├── reports.php
│   │   ├── settings.php
│   │   ├── webhooks.php
│   │   ├── view_contact.php
│   │   └── edit_contact.php
│   ├── includes/           # Shared components
│   │   ├── config.php
│   │   ├── database.php
│   │   ├── auth.php
│   │   └── layout.php
│   └── assets/
│       ├── css/
│       └── js/
├── db/                     # SQLite database (private)
│   └── crm.db
├── tests/                  # Test suite
├── docs/                   # Documentation
└── README.md
```

### Database Schema

#### Contacts Table
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
    evm_address VARCHAR(42),
    twitter_handle VARCHAR(50),
    linkedin_profile VARCHAR(255),
    telegram_username VARCHAR(50),
    discord_username VARCHAR(50),
    github_username VARCHAR(50),
    website VARCHAR(255),
    contact_type VARCHAR(10) DEFAULT 'lead',
    contact_status VARCHAR(20) DEFAULT 'new',
    source VARCHAR(50),
    assigned_to INTEGER,
    notes TEXT,
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

#### Settings Table
```sql
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    show_default_credentials BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

---

## 🚀 Installation & Deployment

### Prerequisites
- **PHP**: 8.0 or higher
- **Extensions**: sqlite3, json, curl, mbstring, openssl, session, pdo, pdo_sqlite
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 512MB RAM, 1GB+ recommended
- **Storage**: 10GB+ available space

### Quick Start (Development)
```bash
# 1. Clone repository
git clone https://github.com/actuallyrizzn/bestjobsinta.com.git
cd bestjobsinta.com

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

### Production Deployment

#### 1. Server Preparation
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 php8.0 php8.0-sqlite3 php8.0-curl php8.0-mbstring php8.0-openssl php8.0-json git unzip

# CentOS/RHEL
sudo yum update -y
sudo yum install httpd php php-sqlite3 php-curl php-mbstring php-openssl php-json git unzip
```

#### 2. Application Deployment
```bash
# Create application directory
sudo mkdir -p /var/www/crm
sudo chown $USER:$USER /var/www/crm

# Clone repository
cd /var/www/crm
git clone https://github.com/actuallyrizzn/bestjobsinta.com.git .

# Set proper ownership and permissions
sudo chown -R www-data:www-data /var/www/crm
sudo chmod 755 -R /var/www/crm/public
sudo chmod 755 /var/www/crm/db
sudo chmod 644 /var/www/crm/db/crm.db 2>/dev/null || true
```

#### 3. Apache Configuration
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/crm/public
    
    <Directory /var/www/crm/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security: Block access to private directories
    <Directory /var/www/crm/includes>
        Require all denied
    </Directory>
    
    <Directory /var/www/crm/db>
        Require all denied
    </Directory>
    
    <Directory /var/www/crm/tests>
        Require all denied
    </Directory>
    
    <Directory /var/www/crm/docs>
        Require all denied
    </Directory>
</VirtualHost>
```

#### 4. Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/crm/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security: Block access to private directories
    location ~ ^/(includes|db|tests|docs)/ {
        deny all;
    }
}
```

### SSL/HTTPS Setup
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

---

## 🔗 API Reference

### Base Information
- **Base URL**: `https://your-domain.com/api/v1/`
- **Content Type**: `application/json`
- **Authentication**: API key required for all endpoints
- **Rate Limit**: 1000 requests per hour per API key

### Authentication
```http
Authorization: Bearer YOUR_API_KEY
```

### Response Format
```json
{
  "success": true,
  "data": {...}
}
```

### Error Response
```json
{
  "error": "Error description",
  "code": 400
}
```

### Contacts API

#### List Contacts
```http
GET /contacts
```

**Query Parameters:**
- `type` (optional): Filter by contact type (`lead` or `customer`)
- `status` (optional): Filter by contact status
- `source` (optional): Filter by source
- `limit` (optional): Number of records (default: 50)
- `offset` (optional): Number to skip (default: 0)
- `search` (optional): Search in name, email, or company

**Example:**
```bash
curl -X GET "https://your-domain.com/api/v1/contacts?type=lead&limit=10" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Get Contact
```http
GET /contacts/{id}
```

#### Create Contact
```http
POST /contacts
```

**Request Body:**
```json
{
  "first_name": "string (required)",
  "last_name": "string (required)",
  "email": "string (required, unique)",
  "phone": "string (optional)",
  "company": "string (optional)",
  "position": "string (optional)",
  "address": "string (optional)",
  "city": "string (optional)",
  "state": "string (optional)",
  "zip_code": "string (optional)",
  "country": "string (optional)",
  "evm_address": "string (optional, Ethereum address)",
  "twitter_handle": "string (optional)",
  "linkedin_profile": "string (optional)",
  "telegram_username": "string (optional)",
  "discord_username": "string (optional)",
  "github_username": "string (optional)",
  "website": "string (optional)",
  "contact_type": "lead|customer (default: lead)",
  "contact_status": "new|qualified|active|inactive (default: new)",
  "source": "string (optional)",
  "assigned_to": "integer (optional, user ID)",
  "notes": "string (optional)"
}
```

#### Update Contact
```http
PUT /contacts/{id}
```

#### Delete Contact
```http
DELETE /contacts/{id}
```

### Deals API

#### List Deals
```http
GET /deals
```

#### Get Deal
```http
GET /deals/{id}
```

#### Create Deal
```http
POST /deals
```

**Request Body:**
```json
{
  "title": "string (required)",
  "contact_id": "integer (required)",
  "amount": "decimal (optional)",
  "stage": "string (default: prospecting)",
  "probability": "integer (default: 0)",
  "expected_close_date": "date (optional)",
  "assigned_to": "integer (optional)",
  "description": "string (optional)"
}
```

#### Update Deal
```http
PUT /deals/{id}
```

#### Delete Deal
```http
DELETE /deals/{id}
```

### Users API (Admin Only)

#### List Users
```http
GET /users
```

#### Get User
```http
GET /users/{id}
```

#### Create User
```http
POST /users
```

#### Update User
```http
PUT /users/{id}
```

#### Delete User
```http
DELETE /users/{id}
```

### Webhooks API

#### List Webhooks
```http
GET /webhooks
```

#### Create Webhook
```http
POST /webhooks
```

**Request Body:**
```json
{
  "url": "string (required)",
  "events": ["contact.created", "contact.updated"],
  "is_active": "boolean (default: true)"
}
```

#### Test Webhook
```http
POST /webhooks/{id}/test
```

---

## 🔌 Integration Guide

### JavaScript Integration

#### Basic Contact Creation
```javascript
const CRM_API_KEY = 'your_api_key_here';
const CRM_BASE_URL = 'https://your-domain.com/api/v1';

async function createContact(contactData) {
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
    const error = await response.json();
    throw new Error(error.error || 'API Error');
  }
}

// Example usage
const newContact = {
  first_name: 'John',
  last_name: 'Doe',
  email: 'john.doe@example.com',
  company: 'Acme Corp',
  phone: '+1234567890',
  source: 'website_form'
};

createContact(newContact)
  .then(contact => console.log('Contact created:', contact.id))
  .catch(error => console.error('Error:', error));
```

#### React Component
```jsx
import React, { useState } from 'react';

const ContactForm = () => {
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    company: ''
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const contactData = {
      first_name: formData.firstName,
      last_name: formData.lastName,
      email: formData.email,
      company: formData.company,
      source: 'react_form'
    };
    
    try {
      const response = await fetch('https://your-domain.com/api/v1/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${process.env.REACT_APP_CRM_API_KEY}`
        },
        body: JSON.stringify(contactData)
      });
      
      if (response.ok) {
        alert('Contact created successfully!');
        setFormData({ firstName: '', lastName: '', email: '', company: '' });
      } else {
        const error = await response.json();
        alert('Error: ' + error.error);
      }
    } catch (error) {
      alert('Network error. Please try again.');
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        placeholder="First Name"
        value={formData.firstName}
        onChange={(e) => setFormData({...formData, firstName: e.target.value})}
        required
      />
      <input
        type="text"
        placeholder="Last Name"
        value={formData.lastName}
        onChange={(e) => setFormData({...formData, lastName: e.target.value})}
        required
      />
      <input
        type="email"
        placeholder="Email"
        value={formData.email}
        onChange={(e) => setFormData({...formData, email: e.target.value})}
        required
      />
      <input
        type="text"
        placeholder="Company"
        value={formData.company}
        onChange={(e) => setFormData({...formData, company: e.target.value})}
      />
      <button type="submit">Submit</button>
    </form>
  );
};
```

### PHP Integration

#### WordPress Integration
```php
// Add to functions.php
function create_crm_contact($contact_data) {
    $response = wp_remote_post('https://your-domain.com/api/v1/contacts', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . CRM_API_KEY
        ),
        'body' => json_encode($contact_data)
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    return json_decode(wp_remote_retrieve_body($response), true);
}

// Hook into form submission
add_action('wp_ajax_contact_form', 'handle_contact_form');
function handle_contact_form() {
    $crm_data = array(
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'email' => sanitize_email($_POST['email']),
        'source' => 'wordpress_form'
    );
    
    $result = create_crm_contact($crm_data);
    wp_die();
}
```

#### Laravel Integration
```php
// Contact Model
class Contact extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'email', 'company', 'phone', 'source'
    ];
}

// Service Class
class CrmService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.crm.api_key');
        $this->baseUrl = config('services.crm.base_url');
    }

    public function createContact($data)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/contacts', $data);

        return $response->json();
    }
}

// Controller
class ContactController extends Controller
{
    public function store(Request $request, CrmService $crmService)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'company' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20'
        ]);

        $validated['source'] = 'laravel_form';

        try {
            $result = $crmService->createContact($validated);
            return response()->json($result, 201);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

### E-commerce Integration

#### Shopify Webhook
```javascript
// Shopify webhook handler
app.post('/webhooks/crm-contact', async (req, res) => {
  const { customer } = req.body;
  
  const contactData = {
    first_name: customer.first_name,
    last_name: customer.last_name,
    email: customer.email,
    phone: customer.phone,
    company: customer.company,
    source: 'shopify_customer',
    contact_type: 'customer',
    contact_status: 'active'
  };

  try {
    const response = await fetch('https://your-domain.com/api/v1/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.CRM_API_KEY}`
      },
      body: JSON.stringify(contactData)
    });

    if (response.ok) {
      console.log('Contact created in CRM');
      res.status(200).send('OK');
    } else {
      console.error('Failed to create contact');
      res.status(500).send('Error');
    }
  } catch (error) {
    console.error('Webhook error:', error);
    res.status(500).send('Error');
  }
});
```

---

## 🔒 Security & Configuration

### Security Best Practices

#### 1. API Key Management
- Generate unique API keys for each integration
- Rotate API keys regularly
- Never expose API keys in client-side code
- Use environment variables for API keys

#### 2. Input Validation
- Validate all input data on both client and server
- Sanitize user inputs to prevent XSS
- Use prepared statements for database queries
- Implement rate limiting

#### 3. HTTPS Configuration
- Always use HTTPS in production
- Redirect HTTP to HTTPS
- Set secure headers (HSTS, CSP)
- Use secure cookies

#### 4. File Permissions
```bash
# Set proper file permissions
chmod 755 -R public/
chmod 644 db/crm.db
chmod 755 db/
chmod 644 includes/config.php
```

### Configuration

#### Environment Variables
```php
// public/includes/config.php
define('APP_NAME', 'Best Jobs in TA');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://your-domain.com');
define('DEBUG_MODE', false);

// Database
define('DB_PATH', '/var/www/crm/db/crm.db');
define('DB_BACKUP_PATH', '/var/www/crm/db/backup/');

// Security
define('SESSION_NAME', 'crm_session');
define('SESSION_LIFETIME', 3600);
define('API_KEY_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);

// API
define('API_RATE_LIMIT', 1000);
define('API_RATE_WINDOW', 3600);
```

#### Nginx Security Headers
```nginx
# Add to server block
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. "Unexpected end of JSON input" Error
**Problem**: API endpoints returning HTTP 204 with no response body
**Solution**: Fixed in recent updates - all DELETE endpoints now return proper JSON responses

#### 2. Authentication Errors
**Problem**: 401 Unauthorized errors
**Solutions**:
- Ensure API key has `Bearer ` prefix
- Check API key is valid and active
- Verify user has proper permissions

#### 3. Database Permission Errors
**Problem**: SQLite database not writable
**Solution**:
```bash
sudo chown www-data:www-data /var/www/crm/db
sudo chmod 775 /var/www/crm/db
sudo chmod 664 /var/www/crm/db/crm.db
```

#### 4. CORS Issues
**Problem**: Cross-origin requests blocked
**Solution**: Add CORS headers to API responses
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

#### 5. Rate Limiting
**Problem**: Too many requests error
**Solution**: Implement exponential backoff in client code
```javascript
async function apiCallWithRetry(url, options, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.status === 429) {
        const delay = Math.pow(2, i) * 1000;
        await new Promise(resolve => setTimeout(resolve, delay));
        continue;
      }
      return response;
    } catch (error) {
      if (i === maxRetries - 1) throw error;
    }
  }
}
```

### Debug Mode
Enable debug mode for troubleshooting:
```php
define('DEBUG_MODE', true);
```

Check logs for detailed error information.

---

## 🆕 Recent Updates & Fixes

### UI Improvements (Latest)
- ✅ **Mobile hamburger menu** - Responsive navigation for mobile devices
- ✅ **Contact view modes** - Toggle between cards and list view with persistent preference
- ✅ **Simplified action buttons** - Cleaner contact management interface
- ✅ **Searchable dropdowns** - Select2 integration for contact selection in deals
- ✅ **Dedicated edit contact page** - Separate page for editing contacts

### API Fixes (Latest)
- ✅ **Consistent response formats** - All endpoints return standardized JSON responses
- ✅ **DELETE endpoint fixes** - Proper JSON responses for contact, deal, and webhook deletion
- ✅ **Error handling improvements** - Better error messages and status codes
- ✅ **Rate limiting** - API request throttling to prevent abuse

### Security Enhancements
- ✅ **Settings management** - Admin-only settings page to control system behavior
- ✅ **Default credentials toggle** - Option to hide default login credentials in production
- ✅ **Input validation** - Enhanced validation for all user inputs
- ✅ **Session security** - Improved session management and security

### Integration Improvements
- ✅ **Troubleshooting FAQ** - Common integration issues and solutions
- ✅ **Real-world examples** - Updated integration examples with actual fixes
- ✅ **Error handling** - Better error handling in integration code
- ✅ **SSL support** - Proper SSL certificate handling for production

### Database Updates
- ✅ **Settings table** - New table for system-wide settings
- ✅ **Migration support** - Automatic database schema updates
- ✅ **Backup functionality** - Database backup and restore capabilities
- ✅ **Performance optimization** - Improved query performance

---

## 👨‍💻 Development Guide

### Local Development Setup
```bash
# Clone repository
git clone https://github.com/actuallyrizzn/bestjobsinta.com.git
cd bestjobsinta.com

# Start development server
cd public
php -S localhost:8000

# Access application
# http://localhost:8000
# Default: admin/admin123
```

### Code Structure
- **MVC Pattern**: Model-View-Controller architecture
- **Template System**: PHP-based templating with layout system
- **Database Layer**: SQLite with PDO abstraction
- **API Layer**: RESTful API with JSON responses
- **Frontend**: Bootstrap 5 with jQuery and Select2

### Testing
```bash
# Run test suite
cd tests
php run_tests.php

# Run specific test
php run_tests.php --filter=ApiTest
```

### Adding New Features
1. **Database**: Add tables/columns in `database.php`
2. **API**: Add endpoints in `api/v1/index.php`
3. **Frontend**: Add pages in `pages/` directory
4. **Navigation**: Update `layout.php` for new menu items
5. **Documentation**: Update this file with new features

### Deployment Checklist
- [ ] Set `DEBUG_MODE = false`
- [ ] Configure proper file permissions
- [ ] Set up SSL certificate
- [ ] Configure web server (Apache/Nginx)
- [ ] Set up database backup
- [ ] Test all API endpoints
- [ ] Verify security headers
- [ ] Update documentation

---

## 📞 Support

### Getting Help
1. **Documentation**: Check this comprehensive guide first
2. **Issues**: Report bugs on GitHub issues
3. **Discussions**: Use GitHub discussions for questions
4. **Security**: Report security issues privately

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Version History
- **v1.0.0**: Initial release with basic CRM functionality
- **v1.1.0**: Added API endpoints and integration support
- **v1.2.0**: UI improvements and mobile responsiveness
- **v1.3.0**: Security enhancements and settings management

---

**Last Updated**: January 2025  
**Version**: 1.3.0  
**Maintainer**: Best Jobs in TA Team 