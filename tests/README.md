# Best Jobs in TA - Test Suite

This directory contains a comprehensive test suite for the Best Jobs in TA system, designed to ensure reliability and functionality across all components.

## 🧪 Test Structure

```
tests/
├── bootstrap.php           # Test environment setup
├── run_tests.php          # Main test runner
├── phpunit.xml            # PHPUnit configuration
├── unit/                  # Unit tests
│   ├── DatabaseTest.php   # Database operations
│   └── AuthTest.php       # Authentication system
├── api/                   # API integration tests
│   └── ApiTest.php        # API endpoints
├── integration/           # Integration tests
└── README.md             # This file
```

## 🚀 Running Tests

### Quick Start
```bash
# Run all tests
php tests/run_tests.php

# Run specific test suites
php tests/unit/DatabaseTest.php
php tests/unit/AuthTest.php
php tests/api/ApiTest.php
```

### Web Interface
Visit `http://localhost:8000/tests/run_tests.php` for a web-based test runner.

### PHPUnit (Optional)
If you have PHPUnit installed:
```bash
phpunit --configuration tests/phpunit.xml
```

## 📋 Test Categories

### Unit Tests
- **Database Tests**: CRUD operations, transactions, table info
- **Authentication Tests**: User creation, login, API keys, permissions

### API Tests
- **Contacts API**: CRUD operations, lead conversion
- **Deals API**: Pipeline management, relationships
- **Users API**: User management, roles
- **Error Handling**: Invalid requests, authentication failures
- **JSON Responses**: Response format validation

### Integration Tests
- **System Workflow**: Complete user → contact → deal workflow
- **Data Integrity**: Foreign key relationships, constraints

## 🔧 Test Environment

The test suite uses:
- **Test Database**: `db/test_crm.db` (separate from production)
- **Mock Data**: Unique test records with timestamps
- **Cleanup**: Automatic cleanup after each test
- **Isolation**: Tests don't interfere with each other

## 📊 Test Results

Test results are saved to `tests/test_results.json` with:
- Timestamp and duration
- Pass/fail counts per suite
- Overall success rate
- Detailed error information

## 🛠 Configuration

### Environment Variables
- `CRM_TESTING`: Set to `true` during tests
- `DB_PATH`: Points to test database
- `CRM_LOADED`: Prevents duplicate includes

### Test Utilities
The `TestUtils` class provides:
- `createTestUser()`: Create test users with unique data
- `createTestContact()`: Create test contacts
- `createTestDeal()`: Create test deals with proper relationships
- `cleanupTestDatabase()`: Remove test data
- `setupTestDatabase()`: Initialize test environment

## 🐛 Troubleshooting

### Common Issues

1. **Database Locked**: Ensure no other processes are using the test database
2. **Unique Constraints**: Tests use `uniqid()` to avoid conflicts
3. **Missing Dependencies**: Install PHP extensions (curl, sqlite3)
4. **Server Not Running**: API tests require `php -S localhost:8000`

### Debug Mode
Add `?debug=1` to test URLs for verbose output:
```
http://localhost:8000/tests/run_tests.php?debug=1
```

## 📈 Coverage

The test suite covers:
- ✅ Database operations (CRUD, transactions)
- ✅ Authentication (login, API keys, roles)
- ✅ API endpoints (all major endpoints)
- ✅ Error handling (400, 401, 404, 500)
- ✅ Data validation (email, password, required fields)
- ✅ Business logic (lead conversion, deal stages)
- ✅ Security (authentication, authorization)

## 🔮 Future Enhancements

- [ ] Performance tests
- [ ] Load testing
- [ ] Browser automation tests
- [ ] API rate limiting tests
- [ ] Webhook delivery tests
- [ ] Database migration tests

## 📝 Writing New Tests

### Unit Test Template
```php
<?php
require_once __DIR__ . '/../bootstrap.php';

class MyTest {
    public function testSomething() {
        echo "  Testing something... ";
        
        try {
            // Test logic here
            echo "PASS\n";
        } catch (Exception $e) {
            echo "FAIL - " . $e->getMessage() . "\n";
        }
    }
}

// Run if called directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $test = new MyTest();
    $test->testSomething();
}
```

### Best Practices
1. Use unique test data (avoid conflicts)
2. Clean up after tests
3. Test both success and failure cases
4. Provide clear error messages
5. Keep tests independent and isolated

---

**Test Suite Version**: 1.0.0  
**Last Updated**: 2025  
**Maintainer**: Best Jobs in TA Development Team 