# FreeOpsDAO CRM - Complete API Reference

## 📋 Overview

This document provides a complete reference for all API endpoints in the FreeOpsDAO CRM system. The API follows RESTful principles and returns JSON responses.

## 🔗 Base Information

- **Base URL**: `https://your-domain.com/api/v1/`
- **Content Type**: `application/json`
- **Authentication**: API key required for all endpoints
- **Rate Limit**: 1000 requests per hour per API key

## 🔐 Authentication

### Methods

#### 1. Bearer Token (Recommended)
```http
Authorization: Bearer YOUR_API_KEY
```

#### 2. Query Parameter
```
?api_key=YOUR_API_KEY
```

### Getting an API Key
1. Log into the CRM as an admin user
2. Navigate to User Management
3. Generate or copy your API key
4. Keep this key secure

## 📊 Response Format

### Success Response
```json
{
  "id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "created_at": "2025-01-14T12:00:00Z"
}
```

### Error Response
```json
{
  "error": "Error description",
  "code": 400,
  "details": "Additional context (optional)"
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `409` - Conflict
- `500` - Internal Server Error

---

## 👥 Contacts API

### List Contacts
```http
GET /contacts
```

#### Query Parameters
- `type` (optional): Filter by contact type (`lead` or `customer`)
- `status` (optional): Filter by contact status
- `source` (optional): Filter by source
- `assigned_to` (optional): Filter by assigned user ID
- `limit` (optional): Number of records to return (default: 50)
- `offset` (optional): Number of records to skip (default: 0)
- `search` (optional): Search in name, email, or company

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/contacts?type=lead&limit=10" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "contacts": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "company": "Acme Corp",
      "contact_type": "lead",
      "contact_status": "new",
      "source": "website_form",
      "created_at": "2025-01-14T12:00:00Z"
    }
  ],
  "count": 1,
  "total": 1,
  "limit": 10,
  "offset": 0
}
```

### Get Contact
```http
GET /contacts/{id}
```

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/contacts/123" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+1-555-0123",
  "company": "Acme Corp",
  "position": "CEO",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "zip_code": "10001",
  "country": "USA",
  "evm_address": "0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b6",
  "twitter_handle": "@johndoe",
  "linkedin_profile": "https://linkedin.com/in/johndoe",
  "telegram_username": "johndoe",
  "discord_username": "johndoe#1234",
  "github_username": "johndoe",
  "website": "https://johndoe.com",
  "contact_type": "lead",
  "contact_status": "new",
  "source": "website_form",
  "assigned_to": 1,
  "notes": "Interested in enterprise solution",
  "first_purchase_date": null,
  "total_purchases": "0.00",
  "last_purchase_date": null,
  "created_at": "2025-01-14T12:00:00Z",
  "updated_at": "2025-01-14T12:00:00Z"
}
```

### Create Contact
```http
POST /contacts
```

#### Request Body
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

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/contacts" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane@example.com",
    "company": "Tech Corp",
    "phone": "+1-555-0124",
    "evm_address": "0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b6",
    "twitter_handle": "@janesmith",
    "contact_type": "lead",
    "source": "referral"
  }'
```

#### Example Response
```json
{
  "id": 124,
  "first_name": "Jane",
  "last_name": "Smith",
  "email": "jane@example.com",
  "company": "Tech Corp",
  "phone": "+1-555-0124",
  "evm_address": "0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b6",
  "twitter_handle": "@janesmith",
  "contact_type": "lead",
  "contact_status": "new",
  "source": "referral",
  "created_at": "2025-01-14T12:00:00Z"
}
```

### Update Contact
```http
PUT /contacts/{id}
```

#### Request Body
Same as Create Contact (all fields optional)

#### Example Request
```bash
curl -X PUT "https://your-domain.com/api/v1/contacts/123" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_status": "qualified",
    "notes": "Contacted via phone, interested in demo"
  }'
```

### Convert Lead to Customer
```http
PUT /contacts/{id}/convert
```

#### Example Request
```bash
curl -X PUT "https://your-domain.com/api/v1/contacts/123/convert" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "contact_type": "customer",
  "contact_status": "active",
  "first_purchase_date": "2025-01-14",
  "updated_at": "2025-01-14T12:00:00Z"
}
```

### Delete Contact
```http
DELETE /contacts/{id}
```

#### Example Request
```bash
curl -X DELETE "https://your-domain.com/api/v1/contacts/123" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "message": "Contact deleted successfully",
  "id": 123
}
```

---

## 💼 Deals API

### List Deals
```http
GET /deals
```

#### Query Parameters
- `stage` (optional): Filter by deal stage
- `assigned_to` (optional): Filter by assigned user ID
- `contact_id` (optional): Filter by contact ID
- `limit` (optional): Number of records to return (default: 50)
- `offset` (optional): Number of records to skip (default: 0)

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/deals?stage=proposal" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "deals": [
    {
      "id": 1,
      "title": "Enterprise License",
      "contact_id": 123,
      "amount": "5000.00",
      "stage": "proposal",
      "probability": 75,
      "expected_close_date": "2025-02-15",
      "assigned_to": 1,
      "created_at": "2025-01-14T12:00:00Z"
    }
  ],
  "count": 1,
  "total": 1
}
```

### Get Deal
```http
GET /deals/{id}
```

#### Example Response
```json
{
  "id": 1,
  "title": "Enterprise License",
  "contact_id": 123,
  "amount": "5000.00",
  "stage": "proposal",
  "probability": 75,
  "expected_close_date": "2025-02-15",
  "assigned_to": 1,
  "description": "Enterprise software license for 100 users",
  "created_at": "2025-01-14T12:00:00Z",
  "updated_at": "2025-01-14T12:00:00Z",
  "contact": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "company": "Acme Corp"
  }
}
```

### Create Deal
```http
POST /deals
```

#### Request Body
```json
{
  "title": "string (required)",
  "contact_id": "integer (required)",
  "amount": "decimal (optional)",
  "stage": "prospecting|qualification|proposal|negotiation|closed_won|closed_lost (default: prospecting)",
  "probability": "integer 0-100 (optional, default: 0)",
  "expected_close_date": "date (optional, YYYY-MM-DD)",
  "assigned_to": "integer (optional, user ID)",
  "description": "string (optional)"
}
```

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/deals" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Premium Subscription",
    "contact_id": 123,
    "amount": 2500.00,
    "stage": "proposal",
    "probability": 80,
    "expected_close_date": "2025-02-28",
    "description": "Annual premium subscription"
  }'
```

### Update Deal
```http
PUT /deals/{id}
```

#### Request Body
Same as Create Deal (all fields optional)

#### Example Request
```bash
curl -X PUT "https://your-domain.com/api/v1/deals/1" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "stage": "negotiation",
    "probability": 90
  }'
```

### Delete Deal
```http
DELETE /deals/{id}
```

#### Example Request
```bash
curl -X DELETE "https://your-domain.com/api/v1/deals/1" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## 👤 Users API

### List Users (Admin Only)
```http
GET /users
```

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/users" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "users": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@freeopsdao.com",
      "first_name": "Admin",
      "last_name": "User",
      "role": "admin",
      "is_active": true,
      "created_at": "2025-01-14T12:00:00Z"
    }
  ],
  "count": 1
}
```

### Get User
```http
GET /users/{id}
```

#### Example Response
```json
{
  "id": 1,
  "username": "admin",
  "email": "admin@freeopsdao.com",
  "first_name": "Admin",
  "last_name": "User",
  "role": "admin",
  "api_key": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
  "is_active": true,
  "created_at": "2025-01-14T12:00:00Z",
  "updated_at": "2025-01-14T12:00:00Z"
}
```

### Create User (Admin Only)
```http
POST /users
```

#### Request Body
```json
{
  "username": "string (required, unique)",
  "email": "string (required, unique)",
  "password": "string (required, min 8 characters)",
  "first_name": "string (optional)",
  "last_name": "string (optional)",
  "role": "admin|user (default: user)",
  "is_active": "boolean (default: true)"
}
```

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/users" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "sales1",
    "email": "sales1@company.com",
    "password": "securepassword123",
    "first_name": "Sales",
    "last_name": "Rep",
    "role": "user"
  }'
```

### Update User
```http
PUT /users/{id}
```

#### Request Body
```json
{
  "email": "string (optional)",
  "first_name": "string (optional)",
  "last_name": "string (optional)",
  "role": "admin|user (optional)",
  "is_active": "boolean (optional)",
  "password": "string (optional, min 8 characters)"
}
```

### Delete User (Admin Only)
```http
DELETE /users/{id}
```

---

## 🔗 Webhooks API

### List Webhooks
```http
GET /webhooks
```

#### Example Response
```json
{
  "webhooks": [
    {
      "id": 1,
      "url": "https://your-app.com/webhooks/crm",
      "events": "contact.created,contact.updated",
      "is_active": true,
      "created_at": "2025-01-14T12:00:00Z"
    }
  ],
  "count": 1
}
```

### Get Webhook
```http
GET /webhooks/{id}
```

### Create Webhook
```http
POST /webhooks
```

#### Request Body
```json
{
  "url": "string (required, valid URL)",
  "events": "string (required, comma-separated events)",
  "is_active": "boolean (default: true)"
}
```

#### Available Events
- `contact.created` - New contact created
- `contact.updated` - Contact updated
- `contact.deleted` - Contact deleted
- `contact.converted` - Lead converted to customer
- `deal.created` - New deal created
- `deal.updated` - Deal updated
- `deal.deleted` - Deal deleted
- `user.created` - New user created
- `user.updated` - User updated

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/webhooks" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/crm",
    "events": "contact.created,contact.updated,deal.created"
  }'
```

### Update Webhook
```http
PUT /webhooks/{id}
```

#### Request Body
```json
{
  "url": "string (optional)",
  "events": "string (optional)",
  "is_active": "boolean (optional)"
}
```

### Test Webhook
```http
POST /webhooks/{id}/test
```

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/webhooks/1/test" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "message": "Webhook test sent successfully",
  "webhook_id": 1,
  "test_payload": {
    "event": "webhook.test",
    "timestamp": "2025-01-14T12:00:00Z",
    "data": {
      "message": "This is a test webhook"
    }
  }
}
```

### Delete Webhook
```http
DELETE /webhooks/{id}
```

---

## 📊 Reports API

### Get Analytics
```http
GET /reports/analytics
```

#### Query Parameters
- `start_date` (optional): Start date (YYYY-MM-DD)
- `end_date` (optional): End date (YYYY-MM-DD)
- `group_by` (optional): Group by field (day, week, month)

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/reports/analytics?start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response
```json
{
  "analytics": {
    "contacts": {
      "total": 150,
      "leads": 100,
      "customers": 50,
      "conversion_rate": 33.33
    },
    "deals": {
      "total": 75,
      "by_stage": {
        "prospecting": 20,
        "qualification": 15,
        "proposal": 25,
        "negotiation": 10,
        "closed_won": 5
      },
      "total_value": 125000.00,
      "average_value": 1666.67
    },
    "sources": {
      "website_form": 45,
      "referral": 30,
      "event": 25,
      "cold_outreach": 20,
      "other": 30
    }
  },
  "period": {
    "start_date": "2025-01-01",
    "end_date": "2025-01-31"
  }
}
```

### Export Data
```http
GET /reports/export
```

#### Query Parameters
- `type` (required): Data type to export (`contacts`, `deals`, `users`)
- `format` (optional): Export format (`csv`, `json`) - default: csv
- `filters` (optional): JSON string of filters

#### Example Request
```bash
curl -X GET "https://your-domain.com/api/v1/reports/export?type=contacts&format=csv" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

#### Example Response (CSV)
```csv
ID,First Name,Last Name,Email,Company,Contact Type,Status,Source,Created At
1,John,Doe,john@example.com,Acme Corp,lead,new,website_form,2025-01-14T12:00:00Z
2,Jane,Smith,jane@example.com,Tech Corp,customer,active,referral,2025-01-14T12:00:00Z
```

---

## 🔧 Commands API

### Execute Command
```http
POST /commands
```

#### Request Body
```json
{
  "command": "string (required)",
  "parameters": "object (optional)",
  "async": "boolean (default: false)"
}
```

#### Available Commands
- `bulk_import_contacts` - Import contacts from CSV
- `bulk_update_contacts` - Update multiple contacts
- `generate_report` - Generate custom report
- `backup_database` - Create database backup
- `cleanup_old_data` - Remove old records

#### Example Request
```bash
curl -X POST "https://your-domain.com/api/v1/commands" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "command": "bulk_import_contacts",
    "parameters": {
      "csv_data": "first_name,last_name,email\nJohn,Doe,john@example.com",
      "source": "import"
    },
    "async": true
  }'
```

#### Example Response
```json
{
  "request_id": "cmd_1234567890abcdef",
  "status": "pending",
  "message": "Command queued for execution"
}
```

### Get Command Status
```http
GET /commands/{request_id}
```

#### Example Response
```json
{
  "request_id": "cmd_1234567890abcdef",
  "status": "completed",
  "result": {
    "imported": 10,
    "failed": 0,
    "errors": []
  },
  "created_at": "2025-01-14T12:00:00Z",
  "completed_at": "2025-01-14T12:01:00Z"
}
```

---

## 📋 OpenAPI Specification

### Get OpenAPI Schema
```http
GET /openapi.json
```

#### Example Response
```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "FreeOpsDAO CRM API",
    "version": "1.0.0",
    "description": "Complete API for FreeOpsDAO CRM system"
  },
  "servers": [
    {
      "url": "https://your-domain.com/api/v1",
      "description": "Production server"
    }
  ],
  "paths": {
    "/contacts": {
      "get": {
        "summary": "List contacts",
        "parameters": [
          {
            "name": "type",
            "in": "query",
            "schema": {
              "type": "string",
              "enum": ["lead", "customer"]
            }
          }
        ],
        "responses": {
          "200": {
            "description": "Success",
            "content": {
              "application/json": {
                "schema": {
                  "$ref": "#/components/schemas/ContactList"
                }
              }
            }
          }
        }
      }
    }
  },
  "components": {
    "schemas": {
      "Contact": {
        "type": "object",
        "properties": {
          "id": {
            "type": "integer"
          },
          "first_name": {
            "type": "string"
          },
          "last_name": {
            "type": "string"
          },
          "email": {
            "type": "string",
            "format": "email"
          }
        }
      }
    }
  }
}
```

---

## 🚨 Error Codes

### Common Error Responses

#### 400 - Bad Request
```json
{
  "error": "Invalid request data",
  "code": 400,
  "details": "Email is required"
}
```

#### 401 - Unauthorized
```json
{
  "error": "Authentication required",
  "code": 401
}
```

#### 404 - Not Found
```json
{
  "error": "Resource not found",
  "code": 404,
  "details": "Contact with ID 123 not found"
}
```

#### 409 - Conflict
```json
{
  "error": "Resource conflict",
  "code": 409,
  "details": "Contact with this email already exists"
}
```

#### 500 - Internal Server Error
```json
{
  "error": "Internal server error",
  "code": 500,
  "details": "Database connection failed"
}
```

---

## 📝 Rate Limiting

### Limits
- **Default**: 1000 requests per hour per API key
- **Burst**: Up to 100 requests per minute
- **Webhook calls**: Not counted against rate limit

### Rate Limit Headers
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1642168800
```

### Rate Limit Exceeded Response
```json
{
  "error": "Rate limit exceeded",
  "code": 429,
  "details": "Too many requests. Try again in 3600 seconds."
}
```

---

## 🔍 Search and Filtering

### Contact Search
```bash
# Search by name, email, or company
curl -X GET "https://your-domain.com/api/v1/contacts?search=john" \
  -H "Authorization: Bearer YOUR_API_KEY"

# Filter by multiple criteria
curl -X GET "https://your-domain.com/api/v1/contacts?type=lead&status=new&source=website_form" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### Deal Filtering
```bash
# Filter by stage and amount range
curl -X GET "https://your-domain.com/api/v1/deals?stage=proposal&min_amount=1000&max_amount=10000" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

---

## 📚 SDK Examples

### JavaScript/Node.js
```javascript
class CRMClient {
  constructor(apiKey, baseUrl = 'https://your-domain.com/api/v1') {
    this.apiKey = apiKey;
    this.baseUrl = baseUrl;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const response = await fetch(url, {
      ...options,
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json',
        ...options.headers
      }
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'API request failed');
    }

    return response.json();
  }

  async createContact(contactData) {
    return this.request('/contacts', {
      method: 'POST',
      body: JSON.stringify(contactData)
    });
  }

  async getContacts(filters = {}) {
    const params = new URLSearchParams(filters);
    return this.request(`/contacts?${params}`);
  }
}

// Usage
const crm = new CRMClient('your-api-key');
const contact = await crm.createContact({
  first_name: 'John',
  last_name: 'Doe',
  email: 'john@example.com'
});
```

### Python
```python
import requests

class CRMClient:
    def __init__(self, api_key, base_url='https://your-domain.com/api/v1'):
        self.api_key = api_key
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json'
        }

    def request(self, endpoint, method='GET', data=None):
        url = f"{self.base_url}{endpoint}"
        response = requests.request(
            method=method,
            url=url,
            headers=self.headers,
            json=data
        )
        
        if not response.ok:
            error = response.json()
            raise Exception(error.get('error', 'API request failed'))
        
        return response.json()

    def create_contact(self, contact_data):
        return self.request('/contacts', method='POST', data=contact_data)

    def get_contacts(self, filters=None):
        params = filters or {}
        query_string = '&'.join([f"{k}={v}" for k, v in params.items()])
        endpoint = f"/contacts?{query_string}" if query_string else "/contacts"
        return self.request(endpoint)

# Usage
crm = CRMClient('your-api-key')
contact = crm.create_contact({
    'first_name': 'John',
    'last_name': 'Doe',
    'email': 'john@example.com'
})
```

---

## 🔮 Webhook Payload Examples

### Contact Created
```json
{
  "event": "contact.created",
  "timestamp": "2025-01-14T12:00:00Z",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "contact_type": "lead",
    "source": "website_form"
  }
}
```

### Contact Converted
```json
{
  "event": "contact.converted",
  "timestamp": "2025-01-14T12:00:00Z",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "contact_type": "customer",
    "first_purchase_date": "2025-01-14"
  }
}
```

### Deal Created
```json
{
  "event": "deal.created",
  "timestamp": "2025-01-14T12:00:00Z",
  "data": {
    "id": 1,
    "title": "Enterprise License",
    "contact_id": 123,
    "amount": "5000.00",
    "stage": "prospecting",
    "probability": 25
  }
}
```

---

**API Version**: 1.0.0  
**Last Updated**: 2025  
**Documentation Version**: Complete API Reference v1.0 