"""
Pytest configuration for Live Tests
Best Jobs in TA - Browser-based testing with Playwright
"""

import pytest
import os
import sys
import subprocess
from playwright.sync_api import Playwright, Browser, Page

# Add the project root to Python path
project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, project_root)

# Server configuration
SERVER_HOST = "localhost"
SERVER_PORT = 8000
SERVER_URL = f"http://{SERVER_HOST}:{SERVER_PORT}"

# Test database configuration
TEST_DB_PATH = os.path.join(project_root, "db", "test_crm.db")


@pytest.fixture(scope="session")
def server_process():
    """Start the PHP server for testing"""
    # Start PHP server in background
    server_cmd = [
        "php",
        "-S",
        f"{SERVER_HOST}:{SERVER_PORT}",
        "-t",
        os.path.join(project_root, "public")
    ]

    # Start server process
    process = subprocess.Popen(
        server_cmd,
        cwd=project_root,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )

    # Wait a moment for server to start
    import time
    time.sleep(2)

    yield process

    # Cleanup: terminate server
    process.terminate()
    process.wait()


@pytest.fixture(scope="function")
def browser_context(browser: Browser):
    """Create a fresh browser context for each test"""
    context = browser.new_context(
        viewport={'width': 1280, 'height': 720},
        ignore_https_errors=True
    )
    yield context
    context.close()


@pytest.fixture(scope="function")
def page(browser_context):
    """Create a new page for each test"""
    page = browser_context.new_page()
    yield page
    page.close()


@pytest.fixture(scope="function")
def authenticated_page(page, server_process):
    """Create an authenticated page for tests that need login"""
    # Navigate to login page
    page.goto(f"{SERVER_URL}/login.php")

    # Fill login form (assuming default admin credentials)
    page.fill("input[name='username']", "admin")
    page.fill("input[name='password']", "admin123")
    page.click("button[type='submit']")

    # Wait for redirect to dashboard
    page.wait_for_url("**/index.php?page=dashboard")

    return page


def pytest_configure(config):
    """Configure pytest for live tests"""
    # Ensure test database exists and is clean
    if not os.path.exists(TEST_DB_PATH):
        os.makedirs(os.path.dirname(TEST_DB_PATH), exist_ok=True)

        # Initialize test database by running a quick setup
        # This would normally be done by the application, but for testing we need it ready
        pass
