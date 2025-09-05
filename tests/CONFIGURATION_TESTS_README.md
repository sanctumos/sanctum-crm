# Configuration Tests Documentation

This document describes the comprehensive test suite for the Sanctum CRM first boot configuration system.

## Test Structure

### Unit Tests
Located in `tests/unit/`

#### ConfigManagerTest.php
Tests for the `ConfigManager` class functionality:
- **Instance Management**: Singleton pattern, instance consistency
- **Configuration CRUD**: Set, get, update, delete configurations
- **Data Types**: String, integer, float, boolean, array, null values
- **Encryption**: Encrypted configuration storage and retrieval
- **Categories**: Category-based configuration management
- **Company Info**: Company information management
- **First Boot Detection**: Installation state detection
- **Installation Progress**: Step completion tracking
- **Cache Management**: Configuration caching and invalidation
- **Edge Cases**: Special characters, large data, concurrent access

#### InstallationManagerTest.php
Tests for the `InstallationManager` class functionality:
- **First Boot Detection**: Installation state validation
- **Step Management**: Current step tracking and progression
- **Environment Validation**: PHP version, extensions, server requirements
- **Database Initialization**: Database setup and table creation
- **Company Setup**: Company information configuration
- **Admin User Creation**: User account creation with validation
- **Default Configuration**: System default settings setup
- **Installation Completion**: Full installation process
- **Reset Functionality**: Installation state reset
- **Status Tracking**: Installation progress monitoring
- **Step Validation**: Form validation for each installation step

#### EnvironmentDetectorTest.php
Tests for the `EnvironmentDetector` class functionality:
- **Web Server Detection**: Apache, Nginx, IIS, Lighttpd identification
- **PHP Version Detection**: Version parsing and validation
- **Extension Detection**: Required PHP extensions checking
- **OS Detection**: Windows, Linux, macOS identification
- **Environment Info**: Comprehensive system information gathering
- **Recommendations**: Configuration recommendations generation
- **Deployment Guides**: Platform-specific setup instructions
- **Production Readiness**: System readiness assessment
- **Memory Parsing**: Memory limit parsing and validation
- **Consistency Checks**: Data consistency validation

### Integration Tests
Located in `tests/integration/`

#### FirstBootIntegrationTest.php
Tests for complete first boot installation flow:
- **Complete Flow**: End-to-end installation process
- **Configuration Persistence**: Data persistence across instances
- **Installation State Management**: Step completion with data storage
- **Installation Reset**: Complete installation reset functionality
- **Configuration Validation**: Form validation and error handling
- **Database Schema Creation**: Table creation and structure validation
- **Default Configuration Setup**: System defaults application
- **Status Tracking**: Installation progress monitoring
- **Concurrent Access**: Multi-instance configuration access
- **Error Handling**: Invalid data and error condition handling

### End-to-End Tests
Located in `tests/e2e/`

#### InstallationWizardE2ETest.php
Tests for the installation wizard user interface:
- **Wizard Access**: Installation page accessibility
- **Step Validation**: Each installation step validation
- **Database Initialization**: Database setup through UI
- **Company Setup**: Company information configuration through UI
- **Admin Creation**: Admin user creation through UI
- **Installation Completion**: Full installation through UI
- **Complete Flow**: End-to-end installation process
- **Form Validation**: Client-side and server-side validation
- **Step Progression**: Proper step sequencing
- **Installation Reset**: Reset functionality through UI
- **Progress Tracking**: Installation progress display
- **Error Handling**: Error condition handling
- **Security Validation**: XSS and SQL injection protection

### API Tests
Located in `tests/api/`

#### ConfigurationApiTest.php
Tests for configuration management API endpoints:
- **Configuration CRUD**: Create, read, update, delete via API
- **Company Info Management**: Company information API operations
- **Installation Progress**: Installation status API access
- **Data Type Support**: Various data types through API
- **Validation**: API request validation
- **Authentication**: API key authentication
- **Bulk Operations**: Bulk configuration updates
- **Error Handling**: API error responses
- **Security**: Authentication and authorization

## Test Categories

### Unit Tests
- **Purpose**: Test individual classes and methods in isolation
- **Scope**: Single class functionality
- **Dependencies**: Minimal external dependencies
- **Speed**: Fast execution
- **Coverage**: Method-level coverage

### Integration Tests
- **Purpose**: Test interaction between multiple components
- **Scope**: Component integration
- **Dependencies**: Database and configuration system
- **Speed**: Medium execution time
- **Coverage**: Component interaction coverage

### End-to-End Tests
- **Purpose**: Test complete user workflows
- **Scope**: Full application flow
- **Dependencies**: Web server and browser simulation
- **Speed**: Slower execution
- **Coverage**: User journey coverage

### API Tests
- **Purpose**: Test API endpoints and responses
- **Scope**: API functionality
- **Dependencies**: HTTP server simulation
- **Speed**: Medium execution time
- **Coverage**: API endpoint coverage

## Running Tests

### Individual Test Suites
```bash
# Unit tests only
php vendor/bin/phpunit tests/unit/ConfigManagerTest.php

# Integration tests only
php vendor/bin/phpunit tests/integration/FirstBootIntegrationTest.php

# E2E tests only
php vendor/bin/phpunit tests/e2e/InstallationWizardE2ETest.php

# API tests only
php vendor/bin/phpunit tests/api/ConfigurationApiTest.php
```

### Configuration Tests Only
```bash
# Run all configuration tests
php tests/run_configuration_tests.php

# Using PHPUnit with configuration file
php vendor/bin/phpunit -c tests/phpunit-configuration.xml
```

### All Tests
```bash
# Run complete test suite
php tests/run_tests.php
```

## Test Configuration

### PHPUnit Configuration
- **File**: `tests/phpunit-configuration.xml`
- **Coverage**: HTML and Clover reports
- **Logging**: JUnit XML output
- **Memory**: 256M limit
- **Timeout**: 300 seconds

### Test Environment
- **Database**: SQLite test database
- **Isolation**: Each test cleans up after itself
- **Mocking**: Minimal external dependencies
- **Data**: Test-specific data sets

## Test Data Management

### Setup
- Clear all test data before each test
- Create necessary test users and configurations
- Initialize required database tables

### Teardown
- Clean up all test data after each test
- Reset configuration state
- Remove temporary files

### Isolation
- Each test is independent
- No shared state between tests
- Fresh database for each test

## Coverage Goals

### Unit Tests
- **Target**: 95%+ method coverage
- **Focus**: All public methods
- **Edge Cases**: Boundary conditions and error states

### Integration Tests
- **Target**: 90%+ integration coverage
- **Focus**: Component interactions
- **Scenarios**: Common use cases and error paths

### E2E Tests
- **Target**: 80%+ user journey coverage
- **Focus**: Critical user workflows
- **Scenarios**: Happy path and error conditions

### API Tests
- **Target**: 95%+ endpoint coverage
- **Focus**: All API endpoints
- **Scenarios**: Valid and invalid requests

## Best Practices

### Test Naming
- Use descriptive test method names
- Include the scenario being tested
- Indicate expected outcome

### Test Structure
- Arrange, Act, Assert pattern
- Single responsibility per test
- Clear test data setup

### Assertions
- Use specific assertions
- Test both positive and negative cases
- Verify side effects

### Error Testing
- Test error conditions
- Verify error messages
- Test edge cases

## Continuous Integration

### Automated Testing
- Tests run on every commit
- Coverage reports generated
- Test results published

### Quality Gates
- All tests must pass
- Coverage thresholds met
- No critical issues

### Reporting
- Test results dashboard
- Coverage reports
- Performance metrics

## Troubleshooting

### Common Issues
- **Database Locked**: Ensure proper cleanup
- **Memory Issues**: Increase memory limit
- **Timeout**: Increase execution time
- **Dependencies**: Check required extensions

### Debug Mode
- Enable verbose output
- Check test logs
- Use debug assertions
- Inspect test data

### Performance
- Profile slow tests
- Optimize database queries
- Use test data factories
- Parallel test execution

## Maintenance

### Regular Updates
- Update test data
- Review test coverage
- Refactor test code
- Update documentation

### Test Review
- Code review for tests
- Test effectiveness review
- Coverage analysis
- Performance review

### Documentation
- Keep test docs updated
- Document test scenarios
- Update troubleshooting guide
- Maintain best practices
