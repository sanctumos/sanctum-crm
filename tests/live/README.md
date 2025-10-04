# Live Tests - Browser Automation & API Integration

This directory contains **live tests** that use **Playwright** for browser automation and **real API testing** against a running server instance.

## 🧪 Test Categories

### Browser UI Tests (`test_browser_ui.py`)
- **Dashboard loading and navigation**
- **Contact page UI elements and interactions**
- **Enrichment modal functionality**
- **Responsive design testing**
- **Accessibility compliance**
- **JavaScript performance**

### User Workflow Tests (`test_user_workflows.py`)
- **Complete user journeys** from login to task completion
- **Contact management workflows**
- **Error recovery and handling**
- **Bulk operations testing**
- **Mobile workflow testing**

### API Integration Tests (`test_api_integration.py`)
- **Live API endpoint testing** with real server
- **Authentication and authorization**
- **Error handling and validation**
- **Performance and concurrent load testing**

## 🚀 Running Live Tests

### Prerequisites
```bash
# Navigate to live tests directory
cd tests/live

# Activate virtual environment (Windows)
./venv/Scripts/Activate.ps1

# Install dependencies
pip install -r requirements.txt

# Install Playwright browsers
playwright install
```

### Run All Live Tests
```bash
# Run all live tests
pytest

# Run with verbose output
pytest -v

# Run specific test file
pytest test_browser_ui.py -v

# Run with coverage
pytest --cov=public --cov-report=html
```

### Run Against Live Server
```bash
# Start the PHP server first
php -S localhost:8000 -t public

# Run tests in another terminal
pytest test_browser_ui.py::TestBrowserUI::test_dashboard_loads
```

## 🔧 Configuration

### Server Configuration
- **Host**: `localhost`
- **Port**: `8000`
- **Base URL**: `http://localhost:8000`

### Test Database
- Tests use the production database (`db/crm.db`)
- Admin credentials: `admin` / `admin123`
- API key automatically retrieved from database

## 📋 Test Structure

```
tests/live/
├── conftest.py              # Pytest configuration and fixtures
├── test_browser_ui.py       # Browser automation tests
├── test_user_workflows.py   # End-to-end user journey tests
├── test_api_integration.py  # Live API testing
├── requirements.txt         # Python dependencies
└── README.md               # This file
```

## 🎯 Test Coverage

### Browser UI Tests
- ✅ **Page Loading**: Dashboard, contacts, navigation
- ✅ **UI Elements**: Buttons, modals, forms, status indicators
- ✅ **JavaScript**: Enrichment functions, error handling
- ✅ **Responsive Design**: Mobile viewport testing
- ✅ **Accessibility**: ARIA labels, keyboard navigation

### User Workflow Tests
- ✅ **Complete Journeys**: Login → Navigate → Action → Complete
- ✅ **Error Recovery**: Invalid inputs, API failures
- ✅ **Bulk Operations**: Multi-contact actions
- ✅ **Mobile Workflows**: Touch interface testing

### API Integration Tests
- ✅ **Live Endpoints**: Real server responses
- ✅ **Authentication**: Bearer token validation
- ✅ **Performance**: Response times, concurrent requests
- ✅ **Error Handling**: Invalid requests, missing data

## 🛠 Troubleshooting

### Common Issues

1. **Server Not Running**
   ```bash
   # Start server in separate terminal
   php -S localhost:8000 -t public
   ```

2. **No API Key Found**
   - Ensure admin user exists in database
   - Check `get_admin_api_key.php` script

3. **Browser Installation**
   ```bash
   playwright install chromium
   playwright install firefox
   ```

4. **Virtual Environment**
   ```bash
   # Windows
   ./venv/Scripts/Activate.ps1
   # Linux/Mac
   source venv/bin/activate
   ```

### Debug Mode
```bash
# Run with debug output
pytest -s -v

# Run specific failing test
pytest test_browser_ui.py::TestBrowserUI::test_dashboard_loads -s
```

## 🔮 Future Enhancements

- [ ] **Visual Regression Tests** (screenshot comparisons)
- [ ] **Network Request Monitoring** (API call tracking)
- [ ] **Database State Validation** (before/after snapshots)
- [ ] **Multi-browser Testing** (Chrome, Firefox, Safari)
- [ ] **Headless CI Integration** (GitHub Actions, Jenkins)
- [ ] **Performance Benchmarking** (baseline comparisons)

## 📊 Test Results

Test results are saved to:
- **HTML Report**: `htmlcov/index.html` (coverage report)
- **JUnit XML**: `junit.xml` (CI integration)
- **Console Output**: Real-time progress and results

## 🔑 Authentication

Tests automatically:
1. Query admin API key from production database
2. Use Bearer token authentication for API tests
3. Handle authentication failures gracefully
4. Skip authenticated tests if no API key available

---

**Live Test Suite Version**: 1.0.0
**Framework**: Playwright + Pytest
**Target**: Real browser and server integration
**Last Updated**: October 2025
