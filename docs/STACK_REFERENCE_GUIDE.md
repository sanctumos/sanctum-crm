# API-First Database-Driven Application Stack Reference

## Overview
This document outlines the proven stack architecture from the FreeOpsDAO CRM project, designed for building scalable, API-first database applications. This pattern is ideal for inventory systems, CRMs, ERPs, and other data-driven web applications.

## 🏗️ Architecture Pattern

### Core Philosophy
- **API-First Design**: All data operations go through RESTful APIs
- **Database-Driven**: Direct SQLite access for performance
- **Hybrid Web Interface**: Direct DB reads + API writes
- **Server-Agnostic**: Works on Apache, Nginx, or PHP built-in server

## 🛠️ Technology Stack

### Backend
- **Language**: PHP 8.0+
- **Database**: SQLite3 (direct extension, no PDO)
- **Web Server**: Nginx (recommended) or Apache
- **Architecture**: Custom MVC-like pattern

### Frontend
- **UI Framework**: Bootstrap 5.x
- **JavaScript**: Vanilla JS (no heavy frameworks)
- **Styling**: CSS3 with modern components

### Development
- **Testing**: PHPUnit for unit/integration tests
- **Documentation**: OpenAPI specification
- **Version Control**: Git

## 📁 Project Structure

```
project-root/
├── public/                    # Web root (only public files)
│   ├── index.php             # Main entry point
│   ├── router.php            # Simple routing logic
│   ├── api/
│   │   └── v1/
│   │       └── index.php     # RESTful API endpoint
│   ├── pages/                # Web interface pages
│   ├── assets/               # Static resources
│   └── includes/             # Shared PHP components
├── includes/                  # Private PHP includes
│   ├── config.php            # Application configuration
│   ├── database.php          # Database handler
│   └── auth.php              # Authentication system
├── db/                       # SQLite database (private)
├── tests/                    # Test suite
└── docs/                     # Documentation
```

## 🔧 Core Components

### 1. Database Layer (`includes/database.php`)
```php
class Database {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // CRUD operations
    public function fetchAll($sql, $params = []) { /* ... */ }
    public function fetchOne($sql, $params = []) { /* ... */ }
    public function insert($table, $data) { /* ... */ }
    public function update($table, $data, $where, $whereParams = []) { /* ... */ }
    public function delete($table, $where, $params = []) { /* ... */ }
}
```

### 2. Authentication System (`includes/auth.php`)
```php
class Auth {
    // Session-based for web interface
    // API key-based for programmatic access
    public function authenticateApiKey($apiKey) { /* ... */ }
    public function requireAuth() { /* ... */ }
    public function requireAdmin() { /* ... */ }
}
```

### 3. API Handler (`api/v1/index.php`)
```php
// Manual URL parsing for routing
$pathParts = explode('/', trim($path, '/'));
$resource = $pathParts[2];
$resourceId = $pathParts[3] ?? null;
$action = $pathParts[4] ?? null;

// Route to appropriate handler
switch($resource) {
    case 'inventory':
        handleInventory($method, $resourceId, $input, $auth);
        break;
    case 'categories':
        handleCategories($method, $resourceId, $input, $auth);
        break;
}
```

## 🌐 Routing Architecture

### Nginx Configuration (Recommended)
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/your-project/public;
    index index.php;

    # Main routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API routing
    location /api/ {
        try_files $uri $uri/ /api/v1/index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security
    location ~ ^/(includes|db|tests|docs)/ {
        deny all;
    }
}
```

### PHP Router (`public/router.php`)
```php
<?php
// API routing
if (preg_match('/^\/api\/v1\//', $_SERVER['REQUEST_URI'])) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}

// Static file serving
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (file_exists($path) && !is_dir($path)) {
    return false; // serve static file
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}
```

## 📊 Database Schema Pattern

### Example: Inventory System
```sql
-- Users table (authentication)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    api_key VARCHAR(255) UNIQUE,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Main data table
CREATE TABLE inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    category_id INTEGER,
    quantity INTEGER DEFAULT 0,
    price DECIMAL(10,2),
    description TEXT,
    location VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Supporting tables
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- Activity logging
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(50),
    table_name VARCHAR(50),
    record_id INTEGER,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## 🔌 API Design Pattern

### RESTful Endpoints
```
GET    /api/v1/inventory           # List all items
GET    /api/v1/inventory/123       # Get specific item
POST   /api/v1/inventory           # Create new item
PUT    /api/v1/inventory/123       # Update item
DELETE /api/v1/inventory/123       # Delete item
GET    /api/v1/inventory/123/stock # Custom action
```

### Response Format
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "Product Name",
    "sku": "PROD-001",
    "quantity": 50,
    "price": 29.99
  }
}
```

### Error Handling
```json
{
  "error": "Item not found",
  "code": 404,
  "details": "No inventory item with ID 999"
}
```

## 🎨 Web Interface Pattern

### Hybrid Data Access
```php
// READ operations: Direct database access
$db = Database::getInstance();
$inventory = $db->fetchAll("SELECT * FROM inventory ORDER BY name");

// WRITE operations: API calls via JavaScript
fetch('/api/v1/inventory', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
})
```

### Page Structure
```php
// pages/inventory.php
<?php
$db = Database::getInstance();
$inventory = $db->fetchAll("SELECT * FROM inventory");

renderHeader('Inventory');
// HTML with Bootstrap components
renderFooter();
?>
```

## 🔒 Security Implementation

### Authentication
```php
// API Key authentication
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (strpos($auth, 'Bearer ') === 0) {
        $apiKey = substr($auth, 7);
    }
}

// Session authentication for web
if (!$auth->isAuthenticated()) {
    header('Location: /login.php');
    exit;
}
```

### Input Validation
```php
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
```

## 🧪 Testing Strategy

### Unit Tests
```php
class InventoryTest extends TestCase {
    public function testCreateInventory() {
        $data = [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'quantity' => 10,
            'price' => 29.99
        ];
        
        $result = $this->api->post('/inventory', $data);
        $this->assertTrue($result['success']);
    }
}
```

### Integration Tests
```php
class ApiTest extends TestCase {
    public function testInventoryEndpoints() {
        // Test full CRUD operations
        $this->testCreate();
        $this->testRead();
        $this->testUpdate();
        $this->testDelete();
    }
}
```

## 🚀 Deployment Checklist

### Server Setup
1. **Install Nginx + PHP-FPM**
2. **Configure Nginx** with provided config
3. **Set up SSL** with Let's Encrypt
4. **Configure database** permissions

### Application Setup
1. **Set web root** to `/public` directory
2. **Configure database** path in `includes/config.php`
3. **Set up authentication** (create admin user)
4. **Run tests** to verify installation

### Security Hardening
1. **Block access** to private directories
2. **Set up rate limiting** for API
3. **Configure CORS** headers
4. **Enable HTTPS** only

## 📈 Performance Optimizations

### Database
- **Use indexes** on frequently queried columns
- **Implement pagination** for large datasets
- **Use prepared statements** for all queries

### Caching
- **Session storage** for user data
- **Query result caching** for reports
- **Static asset caching** in Nginx

### API Optimization
- **Rate limiting** (1000 requests/hour)
- **Response compression** (gzip)
- **Connection pooling** for database

## 🔄 Migration from Other Stacks

### From Apache + .htaccess
1. **Replace RewriteRule** with `try_files`
2. **Move security headers** to Nginx config
3. **Update routing logic** in PHP
4. **Test all endpoints**

### From PDO/SQLite
1. **Replace PDO** with direct SQLite3
2. **Update query methods** to use new syntax
3. **Test database operations**
4. **Update connection handling**

## 📚 Best Practices

### Code Organization
- **Single responsibility** for each class
- **Consistent naming** conventions
- **Error handling** at all levels
- **Logging** for debugging

### API Design
- **Consistent response** format
- **Proper HTTP status** codes
- **Comprehensive error** messages
- **Versioning** strategy

### Database Design
- **Normalized schema** design
- **Foreign key** constraints
- **Indexes** on search columns
- **Audit trails** for changes

## 🎯 Customization for Your Project

### For Inventory System
1. **Replace 'contacts'** with 'inventory'
2. **Add category** management
3. **Implement stock** tracking
4. **Add barcode** support

### For CRM System
1. **Keep contact** management
2. **Add deal** pipeline
3. **Implement reporting** features
4. **Add user** management

### For ERP System
1. **Add multiple** modules
2. **Implement workflow** engine
3. **Add reporting** dashboard
4. **Integrate with** external systems

---

**This stack provides a solid foundation for any data-driven web application with excellent performance, security, and maintainability.** 