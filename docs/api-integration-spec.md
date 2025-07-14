# FreeOpsDAO CRM - API Integration Specification

## 📋 Overview

This document provides the technical specification for integrating external contact databases with the FreeOpsDAO CRM system. Use this guide to implement contact funneling from your existing systems.

## 🔗 Base URL

```
https://your-crm-domain.com/api/v1/
```

**Note**: Replace `your-crm-domain.com` with your actual CRM domain.

## 🔐 Authentication

### API Key Authentication
All API requests require authentication using an API key.

#### Method 1: Bearer Token (Recommended)
```http
Authorization: Bearer YOUR_API_KEY
```

#### Method 2: Query Parameter
```
https://your-crm-domain.com/api/v1/contacts?api_key=YOUR_API_KEY
```

### Getting an API Key
1. Log into the CRM system as an admin user
2. Navigate to User Management
3. Generate or copy your API key
4. Keep this key secure and don't share it publicly

## 📤 Contact Creation API

### Endpoint
```
POST /api/v1/contacts
```

### Request Headers
```http
Content-Type: application/json
Authorization: Bearer YOUR_API_KEY
```

### Request Body Schema

#### Required Fields
```json
{
  "first_name": "string (required)",
  "last_name": "string (required)", 
  "email": "string (required, unique)"
}
```

#### Optional Fields
```json
{
  "phone": "string",
  "company": "string",
  "address": "string",
  "city": "string",
  "state": "string", 
  "zip_code": "string",
  "country": "string",
  "evm_address": "string (Ethereum address)",
  "twitter_handle": "string",
  "linkedin_profile": "string",
  "telegram_username": "string",
  "discord_username": "string",
  "github_username": "string",
  "website": "string",
  "contact_type": "lead|customer (default: lead)",
  "contact_status": "new|qualified|active|inactive (default: new)",
  "source": "string (e.g., 'website_form', 'event', 'referral')",
  "notes": "string"
}
```

### Response Codes

| Code | Description |
|------|-------------|
| 201 | Contact created successfully |
| 400 | Invalid request data |
| 401 | Authentication required |
| 409 | Contact with this email already exists |
| 500 | Server error |

### Example Requests

#### Basic Contact Creation
```bash
curl -X POST https://your-crm-domain.com/api/v1/contacts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "company": "Acme Corp",
    "phone": "+1-555-0123",
    "source": "website_form"
  }'
```

#### Web3 Contact with Social Media
```bash
curl -X POST https://your-crm-domain.com/api/v1/contacts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Alice",
    "last_name": "Crypto",
    "email": "alice@crypto.com",
    "evm_address": "0x742d35Cc6634C0532925a3b8D4C9db96C4b4d8b6",
    "twitter_handle": "@alicecrypto",
    "telegram_username": "alice_crypto",
    "discord_username": "alice#1234",
    "github_username": "alicecrypto",
    "contact_type": "lead",
    "source": "blockchain_event"
  }'
```

### Success Response
```json
{
  "id": 123,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "company": "Acme Corp",
  "phone": "+1-555-0123",
  "contact_type": "lead",
  "contact_status": "new",
  "source": "website_form",
  "created_at": "2025-01-14T12:00:00Z"
}
```

### Error Response
```json
{
  "error": "Contact with this email already exists",
  "code": 409
}
```

## 🔄 Batch Contact Creation

### Endpoint
```
POST /api/v1/contacts/batch
```

### Request Body
```json
{
  "contacts": [
    {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com"
    },
    {
      "first_name": "Jane",
      "last_name": "Smith", 
      "email": "jane@example.com"
    }
  ]
}
```

### Response
```json
{
  "created": 2,
  "failed": 0,
  "results": [
    {
      "email": "john@example.com",
      "status": "created",
      "id": 123
    },
    {
      "email": "jane@example.com", 
      "status": "created",
      "id": 124
    }
  ]
}
```

## 🔍 Contact Lookup

### Check if Contact Exists
```
GET /api/v1/contacts?email=john.doe@example.com
```

### Response
```json
{
  "contacts": [
    {
      "id": 123,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com"
    }
  ],
  "count": 1
}
```

## 🛠 Implementation Examples

### JavaScript/Node.js
```javascript
async function createContact(contactData) {
  const response = await fetch('https://your-crm-domain.com/api/v1/contacts', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${API_KEY}`
    },
    body: JSON.stringify(contactData)
  });
  
  if (response.ok) {
    return await response.json();
  } else {
    throw new Error(`API Error: ${response.status}`);
  }
}

// Usage
const newContact = await createContact({
  first_name: 'John',
  last_name: 'Doe',
  email: 'john@example.com',
  source: 'website_form'
});
```

### PHP
```php
function createContact($contactData) {
    $url = 'https://your-crm-domain.com/api/v1/contacts';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contactData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . API_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        return json_decode($response, true);
    } else {
        throw new Exception("API Error: $httpCode - $response");
    }
}
```

### Python
```python
import requests
import json

def create_contact(contact_data):
    url = 'https://your-crm-domain.com/api/v1/contacts'
    headers = {
        'Content-Type': 'application/json',
        'Authorization': f'Bearer {API_KEY}'
    }
    
    response = requests.post(url, json=contact_data, headers=headers)
    
    if response.status_code == 201:
        return response.json()
    else:
        raise Exception(f"API Error: {response.status_code} - {response.text}")

# Usage
contact = create_contact({
    'first_name': 'John',
    'last_name': 'Doe', 
    'email': 'john@example.com',
    'source': 'website_form'
})
```

## 🎯 Integration Patterns

### 1. Contact Form Integration
```html
<form id="contactForm">
  <input type="text" name="first_name" required>
  <input type="text" name="last_name" required>
  <input type="email" name="email" required>
  <button type="submit">Submit</button>
</form>

<script>
document.getElementById('contactForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const contactData = {
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    source: 'website_form'
  };
  
  try {
    const result = await createContact(contactData);
    alert('Contact created successfully!');
  } catch (error) {
    alert('Error creating contact: ' + error.message);
  }
});
</script>
```

### 2. Event Registration Integration
```javascript
async function registerForEvent(eventData) {
  // Your existing event registration logic
  
  // Also create contact in CRM
  const contactData = {
    first_name: eventData.firstName,
    last_name: eventData.lastName,
    email: eventData.email,
    source: 'event_registration',
    notes: `Registered for event: ${eventData.eventName}`
  };
  
  try {
    await createContact(contactData);
  } catch (error) {
    console.error('Failed to create CRM contact:', error);
    // Don't fail the event registration if CRM fails
  }
}
```

### 3. Newsletter Signup Integration
```javascript
async function newsletterSignup(email, firstName, lastName) {
  const contactData = {
    first_name: firstName,
    last_name: lastName,
    email: email,
    contact_type: 'lead',
    contact_status: 'new',
    source: 'newsletter_signup'
  };
  
  try {
    await createContact(contactData);
    // Continue with newsletter signup
  } catch (error) {
    // Handle error
  }
}
```

## 🔒 Security Considerations

### API Key Security
- Store API keys securely (environment variables, secure config)
- Never expose API keys in client-side code
- Rotate API keys regularly
- Use different API keys for different environments

### Rate Limiting
- Implement reasonable rate limiting (max 100 requests/minute)
- Handle 429 (Too Many Requests) responses gracefully
- Implement exponential backoff for retries

### Data Validation
- Validate all data before sending to API
- Sanitize user inputs
- Handle API errors gracefully

## 📊 Error Handling

### Common Error Scenarios
```javascript
async function handleApiError(response) {
  switch (response.status) {
    case 400:
      console.error('Invalid data:', await response.json());
      break;
    case 401:
      console.error('Authentication failed - check API key');
      break;
    case 409:
      console.error('Contact already exists');
      break;
    case 429:
      console.error('Rate limit exceeded - wait before retrying');
      break;
    case 500:
      console.error('Server error - try again later');
      break;
  }
}
```

### Retry Logic
```javascript
async function createContactWithRetry(contactData, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await createContact(contactData);
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      
      // Wait before retrying (exponential backoff)
      await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
    }
  }
}
```

## 📈 Monitoring & Analytics

### Track Integration Success
```javascript
function trackCrmIntegration(success, contactData, error = null) {
  // Send to your analytics service
  analytics.track('crm_contact_created', {
    success: success,
    source: contactData.source,
    error: error?.message
  });
}
```

### Health Checks
```javascript
async function checkCrmHealth() {
  try {
    const response = await fetch('https://your-crm-domain.com/api/v1/health');
    return response.ok;
  } catch (error) {
    return false;
  }
}
```

## 📞 Support

For technical support or questions about this integration:

- **Documentation**: Check this spec and the main CRM documentation
- **API Testing**: Use the provided examples to test your integration
- **Error Logs**: Check your application logs for detailed error messages
- **Contact**: Reach out to the CRM system administrator

## 🔄 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-01-14 | Initial specification |

---

**Last Updated**: January 14, 2025  
**API Version**: v1  
**Maintainer**: FreeOpsDAO Development Team 