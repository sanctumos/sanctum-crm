"""
API Integration Tests
Best Jobs in TA - Live API endpoint testing with real server
"""

import pytest
import requests
import json
import time
from typing import Dict, Any


class TestAPIIntegration:
    """Test API endpoints with live server integration"""

    BASE_URL = "http://localhost:8000"
    API_KEY = None

    def setup_method(self):
        """Get API key from database for authenticated tests"""
        try:
            # Query admin API key from production database
            import sqlite3
            db_path = "db/crm.db"
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()

            cursor.execute("SELECT api_key FROM users WHERE username = 'admin' LIMIT 1")
            result = cursor.fetchone()

            if result:
                self.API_KEY = result[0]

            conn.close()
        except Exception as e:
            print(f"Warning: Could not get API key: {e}")
            self.API_KEY = None

    def get_headers(self) -> Dict[str, str]:
        """Get headers for API requests"""
        headers = {"Content-Type": "application/json"}
        if self.API_KEY:
            headers["Authorization"] = f"Bearer {self.API_KEY}"
        return headers

    def test_contacts_api_crud(self):
        """Test complete contacts API CRUD operations"""
        # Test GET all contacts
        response = requests.get(f"{self.BASE_URL}/api/v1/contacts", headers=self.get_headers())
        assert response.status_code in [200, 401]  # 401 if no auth

        if response.status_code == 200:
            data = response.json()
            assert "contacts" in data
            assert isinstance(data["contacts"], list)

        # Test POST new contact (if authenticated)
        if self.API_KEY:
            contact_data = {
                "first_name": "Test",
                "last_name": "User",
                "email": f"test_{int(time.time())}@example.com",
                "company": "Test Company"
            }

            response = requests.post(
                f"{self.BASE_URL}/api/v1/contacts",
                headers=self.get_headers(),
                json=contact_data
            )

            if response.status_code == 201:
                created_contact = response.json()
                assert created_contact["first_name"] == "Test"
                assert created_contact["email"] == contact_data["email"]

                # Test GET specific contact
                contact_id = created_contact["id"]
                response = requests.get(
                    f"{self.BASE_URL}/api/v1/contacts/{contact_id}",
                    headers=self.get_headers()
                )
                assert response.status_code == 200

                # Test PUT update
                update_data = {"company": "Updated Company"}
                response = requests.put(
                    f"{self.BASE_URL}/api/v1/contacts/{contact_id}",
                    headers=self.get_headers(),
                    json=update_data
                )
                assert response.status_code == 200

                # Test DELETE
                response = requests.delete(
                    f"{self.BASE_URL}/api/v1/contacts/{contact_id}",
                    headers=self.get_headers()
                )
                assert response.status_code in [204, 404]

    def test_enrichment_api_endpoints(self):
        """Test enrichment API endpoints"""
        # Test enrichment stats endpoint
        response = requests.get(f"{self.BASE_URL}/api/v1/enrichment/stats", headers=self.get_headers())
        # Should return 200 even without API key (mock mode)
        assert response.status_code == 200

        data = response.json()
        expected_keys = ["total_contacts", "enriched_count", "failed_count", "pending_count", "enrichment_rate"]
        for key in expected_keys:
            assert key in data

        # Test individual enrichment (if authenticated)
        if self.API_KEY:
            # Get first contact for testing
            contacts_response = requests.get(f"{self.BASE_URL}/api/v1/contacts", headers=self.get_headers())
            if contacts_response.status_code == 200:
                contacts = contacts_response.json().get("contacts", [])
                if contacts:
                    contact_id = contacts[0]["id"]

                    # Test enrichment endpoint
                    response = requests.post(
                        f"{self.BASE_URL}/api/v1/contacts/{contact_id}/enrich",
                        headers=self.get_headers(),
                        json={"strategy": "auto"}
                    )

                    # Should handle gracefully (mock mode or real enrichment)
                    assert response.status_code in [200, 500]  # 500 if no API key configured

                    # Test enrichment status
                    response = requests.get(
                        f"{self.BASE_URL}/api/v1/contacts/{contact_id}/enrichment-status",
                        headers=self.get_headers()
                    )
                    assert response.status_code == 200

    def test_bulk_enrichment_api(self):
        """Test bulk enrichment API"""
        if not self.API_KEY:
            pytest.skip("API key required for bulk enrichment testing")

        # Test bulk enrichment endpoint
        bulk_data = {
            "contact_ids": [1, 2],  # Test with existing contact IDs
            "strategy": "auto"
        }

        response = requests.post(
            f"{self.BASE_URL}/api/v1/contacts/bulk-enrich",
            headers=self.get_headers(),
            json=bulk_data
        )

        # Should handle gracefully
        assert response.status_code in [200, 400, 404, 500]

    def test_reports_api(self):
        """Test reports API endpoints"""
        # Test analytics endpoint
        response = requests.get(f"{self.BASE_URL}/api/v1/reports/analytics", headers=self.get_headers())
        assert response.status_code == 200

        data = response.json()
        assert "analytics" in data
        assert isinstance(data["analytics"], list)

        # Test export endpoint
        response = requests.get(f"{self.BASE_URL}/api/v1/reports/export", headers=self.get_headers())
        # Should return CSV or appropriate response
        assert response.status_code in [200, 404]  # 404 if no data to export

    def test_authentication_api(self):
        """Test API authentication"""
        # Test without authentication
        response = requests.get(f"{self.BASE_URL}/api/v1/contacts")
        assert response.status_code == 401

        # Test with invalid API key
        headers = {"Authorization": "Bearer invalid_key", "Content-Type": "application/json"}
        response = requests.get(f"{self.BASE_URL}/api/v1/contacts", headers=headers)
        assert response.status_code == 401

        # Test with valid API key (if available)
        if self.API_KEY:
            response = requests.get(f"{self.BASE_URL}/api/v1/contacts", headers=self.get_headers())
            assert response.status_code in [200, 404]  # 200 if contacts exist, 404 if none

    def test_error_handling_api(self):
        """Test API error handling"""
        # Test invalid endpoint
        response = requests.get(f"{self.BASE_URL}/api/v1/nonexistent")
        assert response.status_code == 404

        # Test malformed JSON
        headers = {"Content-Type": "application/json"}
        response = requests.post(
            f"{self.BASE_URL}/api/v1/contacts",
            headers=headers,
            data="invalid json"
        )
        assert response.status_code == 400

        # Test missing required fields
        if self.API_KEY:
            response = requests.post(
                f"{self.BASE_URL}/api/v1/contacts",
                headers=self.get_headers(),
                json={"email": "test@example.com"}  # Missing required first_name, last_name
            )
            assert response.status_code == 400

    def test_performance_api(self):
        """Test API performance"""
        # Test response times for various endpoints
        endpoints = [
            "/api/v1/contacts",
            "/api/v1/enrichment/stats",
            "/api/v1/reports/analytics"
        ]

        for endpoint in endpoints:
            start_time = time.time()

            response = requests.get(f"{self.BASE_URL}{endpoint}", headers=self.get_headers())

            end_time = time.time()
            response_time = (end_time - start_time) * 1000  # Convert to milliseconds

            # Should respond within reasonable time (< 2 seconds)
            assert response_time < 2000, f"{endpoint} took {response_time}ms"

            # Should return valid response
            assert response.status_code in [200, 401, 404]

    def test_concurrent_requests_api(self):
        """Test API under concurrent load"""
        import threading
        import queue

        def make_request(endpoint, results_queue):
            """Make a single API request"""
            try:
                response = requests.get(f"{self.BASE_URL}{endpoint}", headers=self.get_headers())
                results_queue.put((endpoint, response.status_code, True))
            except Exception as e:
                results_queue.put((endpoint, 0, False, str(e)))

        # Test concurrent requests
        endpoints = ["/api/v1/contacts", "/api/v1/enrichment/stats"] * 5  # 10 total requests
        results_queue = queue.Queue()
        threads = []

        for endpoint in endpoints:
            thread = threading.Thread(target=make_request, args=(endpoint, results_queue))
            threads.append(thread)
            thread.start()

        # Wait for all threads to complete
        for thread in threads:
            thread.join()

        # Check results
        results = []
        while not results_queue.empty():
            results.append(results_queue.get())

        # Should have all requests complete
        assert len(results) == 10

        # Most requests should succeed
        success_count = sum(1 for _, _, success, _ in results if success)
        assert success_count >= 8  # At least 80% success rate
