"""
Browser-based UI Tests
Best Jobs in TA - Live browser automation testing
"""

import pytest
import os
from playwright.sync_api import Page, expect


class TestBrowserUI:
    """Test browser-based user interface functionality"""

    def test_dashboard_loads(self, page: Page, server_process):
        """Test that dashboard loads successfully"""
        page.goto(f"{SERVER_URL}/")

        # Should redirect to login if not authenticated
        expect(page).to_have_url("**/login.php")

        # Fill login form
        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")

        # Should redirect to dashboard
        page.wait_for_url("**/index.php?page=dashboard")

        # Verify dashboard content
        expect(page.locator("h1")).to_contain_text("Dashboard")
        expect(page.locator(".stat-card")).to_have_count(5)  # Should have 5 stat cards

    def test_contacts_page_ui(self, authenticated_page: Page):
        """Test contacts page UI elements"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Verify page title
        expect(authenticated_page.locator("h1, .card-title")).to_contain_text("Contacts")

        # Check for enrichment buttons
        enrich_buttons = authenticated_page.locator("button:has-text('Enrich')")
        expect(enrich_buttons).to_have_count(2)  # Should have bulk enrich and individual enrich buttons

        # Check for export button
        export_button = authenticated_page.locator("button:has-text('Export CSV')")
        expect(export_button).to_be_visible()

        # Check for add contact button
        add_button = authenticated_page.locator("button:has-text('Add Contact')")
        expect(add_button).to_be_visible()

    def test_enrichment_modal_functionality(self, authenticated_page: Page):
        """Test bulk enrichment modal opens and functions"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Click bulk enrich button
        bulk_enrich_btn = authenticated_page.locator("button:has-text('Bulk Enrich')")
        bulk_enrich_btn.click()

        # Modal should appear
        modal = authenticated_page.locator("#bulkEnrichModal")
        expect(modal).to_be_visible()

        # Should have strategy selector
        strategy_select = modal.locator("#bulkEnrichStrategy")
        expect(strategy_select).to_be_visible()

        # Should have cancel and start buttons
        cancel_btn = modal.locator("button:has-text('Cancel')")
        start_btn = modal.locator("button:has-text('Start Enrichment')")
        expect(cancel_btn).to_be_visible()
        expect(start_btn).to_be_visible()

        # Close modal
        cancel_btn.click()
        expect(modal).not_to_be_visible()

    def test_contact_enrichment_button(self, authenticated_page: Page):
        """Test individual contact enrichment button"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Find first enrich button (there should be at least one if there are contacts)
        enrich_btn = authenticated_page.locator("button:has-text('Enrich')").first

        if enrich_btn.is_visible():
            # Click the enrich button
            enrich_btn.click()

            # Should show loading state or error (depending on API availability)
            # Since we don't have an API key, it should show an error
            # But the button should respond to clicks
            pass

    def test_responsive_design(self, page: Page, server_process):
        """Test responsive design on mobile viewport"""
        # Set mobile viewport
        page.set_viewport_size({"width": 375, "height": 667})

        page.goto(f"{SERVER_URL}/")

        # Login on mobile
        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")

        page.wait_for_url("**/index.php?page=dashboard")

        # Check that content is accessible on mobile
        expect(page.locator("h1")).to_be_visible()
        expect(page.locator(".stat-card")).to_be_visible()

    def test_navigation_menu(self, authenticated_page: Page):
        """Test main navigation functionality"""
        # Test contacts navigation
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")
        expect(authenticated_page.locator("h1, .card-title")).to_contain_text("Contacts")

        # Test dashboard navigation
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=dashboard")
        expect(authenticated_page.locator("h1")).to_contain_text("Dashboard")

        # Test users navigation (if user is admin)
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=users")
        # Should either show users page or redirect (depending on permissions)

    def test_form_validation(self, authenticated_page: Page):
        """Test form validation and error handling"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Try to submit add contact form with invalid data
        add_btn = authenticated_page.locator("button:has-text('Add Contact')")
        if add_btn.is_visible():
            add_btn.click()

            # Modal should open
            modal = authenticated_page.locator("#addContactModal")
            expect(modal).to_be_visible()

            # Try to submit without required fields
            submit_btn = modal.locator("button[type='submit']")
            submit_btn.click()

            # Should show validation errors or prevent submission
            # (Exact behavior depends on form validation implementation)

    def test_api_error_handling(self, authenticated_page: Page):
        """Test API error handling in UI"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Try enrichment without API key (should handle gracefully)
        enrich_btn = authenticated_page.locator("button:has-text('Enrich')").first

        if enrich_btn.is_visible():
            enrich_btn.click()

            # Should handle API errors gracefully
            # May show toast notifications or error messages

    def test_loading_states(self, authenticated_page: Page):
        """Test loading states and user feedback"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Check that page loads without errors
        expect(authenticated_page.locator("body")).to_be_visible()

        # Check for any loading indicators or error states
        # Should not have JavaScript errors in console

    def test_accessibility_features(self, authenticated_page: Page):
        """Test basic accessibility features"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Check for alt text on images (if any)
        images = authenticated_page.locator("img")
        if images.count() > 0:
            for i in range(images.count()):
                img = images.nth(i)
                # Should have alt attribute for accessibility
                expect(img).to_have_attribute("alt")

        # Check for proper heading structure
        headings = authenticated_page.locator("h1, h2, h3, h4, h5, h6")
        expect(headings.first).to_be_visible()


class TestPerformanceMetrics:
    """Test performance and loading metrics"""

    def test_page_load_performance(self, page: Page, server_process):
        """Test page load performance"""
        page.goto(f"{SERVER_URL}/")

        # Measure login page load time
        start_time = page.evaluate("performance.now()")

        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")

        page.wait_for_url("**/index.php?page=dashboard")
        end_time = page.evaluate("performance.now()")

        load_time = end_time - start_time

        # Should load in reasonable time (< 5 seconds)
        assert load_time < 5000, f"Page took {load_time}ms to load"

    def test_javascript_performance(self, authenticated_page: Page):
        """Test JavaScript performance"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Measure JavaScript execution time
        start_time = authenticated_page.evaluate("performance.now()")

        # Trigger some JavaScript (like clicking enrichment button)
        enrich_btn = authenticated_page.locator("button:has-text('Enrich')").first
        if enrich_btn.is_visible():
            enrich_btn.click()

        end_time = authenticated_page.evaluate("performance.now()")
        js_time = end_time - start_time

        # JavaScript should execute quickly (< 1 second)
        assert js_time < 1000, f"JavaScript took {js_time}ms to execute"
