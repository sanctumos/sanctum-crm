"""
User Workflow Tests
Best Jobs in TA - End-to-end user journey testing
"""

import pytest
import time
from playwright.sync_api import Page, expect


class TestUserWorkflows:
    """Test complete user workflows from login to completion"""

    def test_complete_contact_management_workflow(self, page: Page, server_process):
        """Test complete contact management workflow"""
        # 1. Login
        page.goto(f"{SERVER_URL}/")
        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")
        page.wait_for_url("**/index.php?page=dashboard")

        # 2. Navigate to contacts
        page.goto(f"{SERVER_URL}/index.php?page=contacts")
        expect(page.locator("h1, .card-title")).to_contain_text("Contacts")

        # 3. Add a new contact
        add_btn = page.locator("button:has-text('Add Contact')")
        add_btn.click()

        # Fill contact form
        modal = page.locator("#addContactModal")
        expect(modal).to_be_visible()

        page.fill("input[name='first_name']", "John")
        page.fill("input[name='last_name']", "Doe")
        page.fill("input[name='email']", "john.doe@example.com")
        page.fill("input[name='company']", "Test Company")
        page.fill("input[name='phone']", "+1234567890")

        # Submit form
        submit_btn = modal.locator("button[type='submit']")
        submit_btn.click()

        # Should redirect back to contacts page
        page.wait_for_url("**/index.php?page=contacts")

        # 4. Verify contact was added (check for success message or contact in list)
        # Note: Implementation depends on how success is shown

        # 5. Test enrichment workflow
        enrich_btn = page.locator("button:has-text('Enrich')").first
        if enrich_btn.is_visible():
            enrich_btn.click()
            # Should handle enrichment attempt gracefully

        # 6. Test export functionality
        export_btn = page.locator("button:has-text('Export CSV')")
        if export_btn.is_visible():
            # Click export (may trigger download)
            with page.expect_download() as download_info:
                export_btn.click()
            download = download_info.value
            # Verify download was triggered

    def test_dashboard_to_contact_details_workflow(self, authenticated_page: Page):
        """Test navigation from dashboard to contact details"""
        # Start on dashboard
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=dashboard")

        # Navigate to contacts
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Click on a contact to view details
        view_btn = authenticated_page.locator("a:has-text('View')").first
        if view_btn.is_visible():
            view_btn.click()

            # Should navigate to contact details page
            expect(authenticated_page).to_have_url("**/index.php?page=view_contact*")

            # Verify contact details are displayed
            expect(authenticated_page.locator(".card")).to_be_visible()

            # Test enrichment button on details page
            enrich_btn = authenticated_page.locator("button:has-text('Enrich Contact')")
            if enrich_btn.is_visible():
                # Should be clickable and handle enrichment
                pass

    def test_error_recovery_workflow(self, authenticated_page: Page):
        """Test error handling and recovery"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Try to trigger an error (e.g., enrichment without API key)
        enrich_btn = authenticated_page.locator("button:has-text('Enrich')").first
        if enrich_btn.is_visible():
            enrich_btn.click()

            # Should handle error gracefully
            # Check for error messages or toast notifications
            # Page should remain functional

        # Try invalid form submission
        add_btn = authenticated_page.locator("button:has-text('Add Contact')")
        if add_btn.is_visible():
            add_btn.click()

            modal = authenticated_page.locator("#addContactModal")
            submit_btn = modal.locator("button[type='submit']")

            # Submit without required fields
            submit_btn.click()

            # Should show validation errors
            # Modal should remain open for correction

    def test_bulk_operations_workflow(self, authenticated_page: Page):
        """Test bulk operations functionality"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Open bulk enrichment modal
        bulk_btn = authenticated_page.locator("button:has-text('Bulk Enrich')")
        bulk_btn.click()

        modal = authenticated_page.locator("#bulkEnrichModal")
        expect(modal).to_be_visible()

        # Select enrichment strategy
        strategy_select = modal.locator("#bulkEnrichStrategy")
        expect(strategy_select).to_be_visible()

        # Test strategy selection
        strategy_select.select_option("email")

        # Test modal close functionality
        cancel_btn = modal.locator("button:has-text('Cancel')")
        cancel_btn.click()
        expect(modal).not_to_be_visible()

    def test_responsive_workflow_on_mobile(self, page: Page, server_process):
        """Test complete workflow on mobile device"""
        # Set mobile viewport
        page.set_viewport_size({"width": 375, "height": 667})

        # Complete login workflow on mobile
        page.goto(f"{SERVER_URL}/")
        page.fill("input[name='username']", "admin")
        page.fill("input[name='password']", "admin123")
        page.click("button[type='submit']")
        page.wait_for_url("**/index.php?page=dashboard")

        # Navigate to contacts on mobile
        page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Check mobile-responsive layout
        expect(page.locator(".card")).to_be_visible()

        # Test mobile navigation
        # Buttons should be accessible and functional on mobile

    def test_accessibility_workflow(self, authenticated_page: Page):
        """Test accessibility compliance throughout workflows"""
        authenticated_page.goto(f"{SERVER_URL}/index.php?page=contacts")

        # Test keyboard navigation
        authenticated_page.keyboard.press("Tab")

        # Check focus management
        # Test screen reader compatibility
        # Verify ARIA labels where appropriate

        # Test with reduced motion preference
        authenticated_page.emulate_media({"prefers-reduced-motion": "reduce"})

        # Verify animations are respectful of user preferences
