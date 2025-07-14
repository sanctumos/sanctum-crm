# Basic CRM System Documentation

## Overview
A simple Customer Relationship Management (CRM) system built with PHP, Bootstrap UI, and SQLite database. This system provides essential CRM functionality for small to medium businesses.

## Technology Stack
- **Backend**: PHP 8.0+
- **Frontend**: Bootstrap 5.x
- **Database**: SQLite 3
- **Server**: Apache/Nginx (or PHP built-in server for development)

## System Requirements
- PHP 8.0 or higher
- SQLite3 extension enabled
- Web server (Apache/Nginx) or PHP built-in server
- Modern web browser

## Project Structure
```
crm-system/
├── assets/
│   ├── css/
│   │   └── custom.css
│   ├── js/
│   │   └── main.js
│   └── images/
├── includes/
│   ├── config.php
│   ├── database.php
│   ├── functions.php
│   └── auth.php
├── pages/
│   ├── dashboard.php
│   ├── customers.php
│   ├── leads.php
│   ├── deals.php
│   └── reports.php
├── api/
│   ├── v1/
│   │   └── index.php
│   └── openapi.json
├── db/
│   └── crm.db
├── index.php
├── login.php
└── README.md
```

## Database Schema

### Contacts Table (Combined Leads & Customers)
```sql
CREATE TABLE contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(50),
    -- Social Media & Web3 Fields
    evm_address VARCHAR(42), -- Ethereum address (0x...)
    twitter_handle VARCHAR(50),
    linkedin_profile VARCHAR(255),
    telegram_username VARCHAR(50),
    discord_username VARCHAR(50),
    github_username VARCHAR(50),
    website VARCHAR(255),
    -- Contact Classification
    contact_type ENUM('lead', 'customer') DEFAULT 'lead',
    contact_status VARCHAR(20) DEFAULT 'new', -- new, qualified, active, inactive, etc.
    source VARCHAR(50), -- website, event, referral, cold_outreach, etc.
    assigned_to INTEGER,
    notes TEXT,
    -- Customer-specific fields (NULL for leads)
    first_purchase_date DATE,
    total_purchases DECIMAL(10,2) DEFAULT 0.00,
    last_purchase_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Deals Table
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

### Users Table
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role VARCHAR(20) DEFAULT 'user',
    api_key VARCHAR(255) UNIQUE, -- For MCP/agent authentication
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Core Features

### 1. Contact Management (Leads & Customers)
- Add, edit, and delete contact records
- Search and filter contacts by type (lead/customer)
- View contact details and history
- Lead qualification and conversion to customers
- Contact assignment to team members
- Export contact data

### 3. Deal Management
- Create and track sales opportunities
- Pipeline management with stages
- Deal forecasting and reporting
- Activity tracking

### 4. User Authentication
- Secure login/logout system
- User role management
- Session management
- Password reset functionality

### 5. Reporting and Analytics
- Dashboard with key metrics
- Sales pipeline reports
- Customer activity reports
- Lead conversion reports

## Installation Guide

### Step 1: Environment Setup
1. Ensure PHP 8.0+ is installed
2. Enable SQLite3 extension in php.ini
3. Set up a web server or use PHP built-in server

### Step 2: Project Setup
```bash
# Clone or download the project
cd crm-system

# Set proper permissions
chmod 755 -R assets/
chmod 755 -R database/
chmod 644 database/crm.db

# Start PHP built-in server (for development)
php -S localhost:8000
```

### Step 3: Database Initialization
1. Navigate to the project URL
2. The system will automatically create the database if it doesn't exist
3. Default admin credentials: admin/admin123

### Step 4: Configuration
Edit `includes/config.php` to customize:
- Database path
- Application settings
- Email configuration
- Security settings

## API Endpoints

All API endpoints are versioned and follow RESTful conventions. All responses are JSON with standard HTTP status codes.

### Authentication
All API endpoints require authentication via API key:
- **Header**: `Authorization: Bearer <api_key>` (preferred)
- **Query Param**: `?api_key=<api_key>` (fallback)

### Error Response Format
All errors return JSON with consistent format:
```json
{
  "error": "Error description",
  "code": 400,
  "details": "Additional context (optional)"
}
```

### Contacts API
- `GET /api/v1/contacts` - List all contacts
- `GET /api/v1/contacts/{id}` - Get specific contact
- `GET /api/v1/contacts?type=lead` - List all leads
- `GET /api/v1/contacts?type=customer` - List all customers
- `POST /api/v1/contacts` - Create new contact
- `PUT /api/v1/contacts/{id}` - Update contact
- `PUT /api/v1/contacts/{id}/convert` - Convert lead to customer
- `DELETE /api/v1/contacts/{id}` - Delete contact

### Deals API
- `GET /api/v1/deals` - List all deals
- `GET /api/v1/deals/{id}` - Get specific deal
- `POST /api/v1/deals` - Create new deal
- `PUT /api/v1/deals/{id}` - Update deal
- `DELETE /api/v1/deals/{id}` - Delete deal

### Webhooks API (Future)
- `GET /api/v1/webhooks` - List registered webhooks
- `POST /api/v1/webhooks` - Register new webhook
- `DELETE /api/v1/webhooks/{id}` - Remove webhook

### Commands API (Planned)
- `POST /api/v1/commands` - Execute batch commands
- `GET /api/v1/commands/{request_id}` - Check command status

### OpenAPI Documentation
- `GET /api/openapi.json` - Machine-readable API specification

## MCP Integration Requirements

### 1. **Machine-Friendly Responses**
- All endpoints return JSON (no HTML redirects)
- Consistent error format with HTTP status codes
- Predictable response schemas for agent parsing

### 2. **Token-Based Authentication**
- API keys stored in `users.api_key` field
- Bearer token authentication for non-interactive access
- Automatic API key generation on user creation

### 3. **Versioned Endpoints**
- All endpoints under `/api/v1/` namespace
- Future changes will be versioned to avoid breaking integrations
- Predictable URL patterns for agent discovery

### 4. **Idempotency Support**
- Optional `request_id` header for mutating operations
- Safe retry mechanisms for POST/PUT operations
- Consistent response for duplicate requests

### 5. **Webhook System (Future)**
- Event-driven notifications for contact/deal updates
- Webhook registration and management endpoints
- Support for `contact_created`, `contact_updated`, `deal_closed` events

### 6. **Command Batching**
- Batch operation support via `/api/v1/commands`
- Array of commands with individual status tracking
- Asynchronous operation support for complex workflows

### 7. **OpenAPI/Swagger Specification**
- Machine-readable API documentation
- Auto-generated client libraries
- Agent development acceleration

## Security Considerations

### Authentication
- Password hashing using PHP's password_hash()
- Session-based authentication for web interface
- API key authentication for programmatic access
- CSRF protection on forms
- Input validation and sanitization

### Data Protection
- SQL injection prevention using prepared statements
- XSS protection through output escaping
- File upload restrictions
- HTTPS enforcement for production

### Access Control
- Role-based access control (RBAC)
- User session management
- API authentication for external access
- Audit logging for sensitive operations

## Customization Options

### UI Customization
- Modify Bootstrap theme variables
- Custom CSS in `assets/css/custom.css`
- Responsive design considerations
- Brand colors and logos

### Feature Extensions
- Email integration
- Calendar integration
- Document management
- Advanced reporting
- API integrations

### Database Extensions
- Additional custom fields
- Related tables for complex relationships
- Data import/export functionality
- Backup and restore procedures

## Performance Optimization

### Database Optimization
- Proper indexing on frequently queried columns
- Query optimization
- Database connection pooling
- Regular database maintenance

### Application Optimization
- PHP opcache configuration
- Asset minification and compression
- Caching strategies
- CDN integration for static assets

## Troubleshooting

### Common Issues
1. **Database Connection Errors**
   - Check SQLite3 extension is enabled
   - Verify database file permissions
   - Ensure database directory is writable

2. **Session Issues**
   - Check session directory permissions
   - Verify session configuration in php.ini
   - Clear browser cookies and cache

3. **File Upload Problems**
   - Check upload_max_filesize in php.ini
   - Verify directory permissions
   - Ensure proper file type validation

### Debug Mode
Enable debug mode in `includes/config.php`:
```php
define('DEBUG_MODE', true);
```

## Maintenance

### Regular Tasks
- Database backups
- Log file rotation
- Security updates
- Performance monitoring
- User account cleanup

### Backup Procedures
```bash
# Database backup
cp database/crm.db database/backup/crm_$(date +%Y%m%d_%H%M%S).db

# Full system backup
tar -czf backup_$(date +%Y%m%d).tar.gz crm-system/
```

## Support and Documentation

### Additional Resources
- PHP Documentation: https://www.php.net/docs.php
- Bootstrap Documentation: https://getbootstrap.com/docs/
- SQLite Documentation: https://www.sqlite.org/docs.html

### Contact Information
For technical support or feature requests, please contact the development team.

---

**Version**: 1.0.0  
**Last Updated**: 2025  
**License**: CC-BY-SA