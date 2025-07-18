# FreeOpsDAO CRM - API Integration Specification

## 📋 Overview

This document provides the technical specification for integrating external contact databases with the FreeOpsDAO CRM system. Use this guide to implement contact funneling from your existing systems.

## Project Structure (Web Root)

```
crm.freeopsdao.com/
├── public/                  # Web root (all public files)
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── .htaccess
│   ├── api/
│   │   └── v1/
│   │       └── index.php
│   ├── pages/
│   │   ├── dashboard.php
│   │   ├── contacts.php
│   │   └── error.php
│   └── assets/
│       ├── js/
│       └── css/
├── includes/                # PHP includes (private)
│   ├── config.php
│   ├── database.php
│   └── auth.php
├── db/                      # SQLite DB (private)
│   └── crm.db
├── tests/                   # Test suite (private)
├── docs/                    # Documentation
└── README.md
```

**Note:** All API endpoints are under `/public/api/v1/`.

## 🔗 Base URL

```
https://crm.freeopsdao.com/api/v1/
```

**Note**: This is the production CRM API endpoint.

## 🔐 Authentication

### API Key Authentication
All API requests require authentication using an API key.

#### Method 1: Bearer Token (Recommended)
```http
Authorization: Bearer YOUR_API_KEY
```

#### Method 2: Query Parameter
```
https://crm.freeopsdao.com/api/v1/contacts?api_key=YOUR_API_KEY
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
curl -X POST https://crm.freeopsdao.com/api/v1/contacts \
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
curl -X POST https://crm.freeopsdao.com/api/v1/contacts \
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
  "success": true,
  "contact": {
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
GET https://crm.freeopsdao.com/api/v1/contacts?email=john.doe@example.com
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
  const response = await fetch('https://crm.freeopsdao.com/api/v1/contacts', {
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
    $url = 'https://crm.freeopsdao.com/api/v1/contacts';
    
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
    url = 'https://crm.freeopsdao.com/api/v1/contacts'
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
    const response = await fetch('https://crm.freeopsdao.com/api/v1/health');
    return response.ok;
  } catch (error) {
    return false;
  }
}
```

## 🔧 Integration FAQ - Common Issues & Solutions

This FAQ addresses the most common problems encountered when integrating with the FreeOpsDAO CRM API, based on real troubleshooting experience.

---

### ❓ **Q: I'm getting "SSL certificate problem: unable to get local issuer certificate" errors**

**A:** This is a common local development issue where PHP can't verify the SSL certificate.

**Solution:**
```php
// Disable SSL verification for local testing
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
```

**Note:** Only disable SSL verification in development environments. For production, ensure proper SSL certificates are configured.

---

### ❓ **Q: The API returns "Invalid JSON response: Syntax error" even though the request looks correct**

**A:** The FreeOpsDAO CRM server has a known issue where it may concatenate multiple error responses before the actual success response.

**Problem Response:**
```json
{"error":"Internal server error","code":500,"details":null}{"error":"Internal server error","code":500,"details":null}{"success":true,"contact":{"id":16,...}}
```

**Solution:**
```php
// Extract the actual success response from malformed JSON
$cleanResponse = $response;
if (strpos($response, '{"success":true') !== false) {
    $successStart = strpos($response, '{"success":true');
    if ($successStart !== false) {
        $cleanResponse = substr($response, $successStart);
    }
}
$result = json_decode($cleanResponse, true);
```

---

### ❓ **Q: My contact creation is failing with HTTP 500 errors**

**A:** Check these common causes:

1. **Missing required fields:**
   ```php
   // Required fields
   $data = [
       'first_name' => 'John',      // Required
       'last_name' => 'Doe',        // Required  
       'email' => 'john@example.com' // Required
   ];
   ```

2. **Wrong field names:**
   ```php
   // Correct field names (not 'firstName', 'lastName')
   'first_name' => 'John',
   'last_name' => 'Doe',
   ```

3. **Missing source field:**
   ```php
   // Always include source
   'source' => 'website_form'
   ```

---

### ❓ **Q: The API returns HTTP 201 but my code still fails to parse the response**

**A:** The API response structure is different from what you might expect.

**Actual Response Structure:**
```json
{
  "success": true,
  "contact": {
    "id": 16,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    // ... other fields
  }
}
```

**Correct Parsing:**
```php
if ($httpCode === 201) {
    if (isset($result['success']) && $result['success'] === true && isset($result['contact'])) {
        return ['success' => true, 'data' => $result['contact']];
    }
}
```

---

### ❓ **Q: How do I handle authentication errors?**

**A:** Check your API key and authentication method.

**Common Issues:**
- **Invalid API key:** Verify the key is correct and active
- **Wrong header format:** Use `Authorization: Bearer YOUR_API_KEY`
- **Missing header:** Always include the Authorization header

**Debug Authentication:**
```php
// Test basic connection first
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://crm.freeopsdao.com/api/v1/contacts');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . CRM_API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// HTTP 401 = Authentication failed
// HTTP 403 = Access forbidden
// HTTP 200 = Authentication successful
```

---

### ❓ **Q: What's the correct endpoint URL structure?**

**A:** Use the exact URL structure from the documentation.

**Correct URLs:**
```php
// Base URL
define('CRM_BASE_URL', 'https://crm.freeopsdao.com/api/v1/');

// Contact creation
$url = CRM_BASE_URL . 'contacts';  // https://crm.freeopsdao.com/api/v1/contacts

// Contact lookup
$url = CRM_BASE_URL . 'contacts?email=john@example.com';
```

**Common Mistakes:**
- ❌ `https://crm.freeopsdao.com/api/v1/contacts/` (trailing slash)
- ❌ `https://crm.freeopsdao.com/api/v1/contact` (singular)
- ❌ `https://crm.freeopsdao.com/contacts` (missing /api/v1/)

---

### ❓ **Q: How do I validate that my integration is working?**

**A:** Use this step-by-step testing approach:

1. **Test Basic Connection:**
   ```php
   // Simple GET request to verify API is reachable
   $response = file_get_contents('https://crm.freeopsdao.com/api/v1/contacts');
   ```

2. **Test Authentication:**
   ```php
   // Should return HTTP 200 if authenticated
   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, 'https://crm.freeopsdao.com/api/v1/contacts');
   curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . API_KEY]);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   ```

3. **Test Contact Creation:**
   ```php
   // Minimal test contact
   $testData = [
       'first_name' => 'Test',
       'last_name' => 'User', 
       'email' => 'test-' . time() . '@example.com',
       'source' => 'website_form'
   ];
   ```

---

### ❓ **Q: What are the required vs optional fields?**

**A:** Here's the complete field breakdown:

**Required Fields:**
```php
$required = [
    'first_name',  // string
    'last_name',   // string
    'email'        // string, unique
];
```

**Optional Fields:**
```php
$optional = [
    'phone',              // string
    'company',            // string
    'address',            // string
    'city',               // string
    'state',              // string
    'zip_code',           // string
    'country',            // string
    'evm_address',        // string (Ethereum address)
    'twitter_handle',     // string
    'linkedin_profile',   // string
    'telegram_username',  // string
    'discord_username',   // string
    'github_username',    // string
    'website',            // string
    'contact_type',       // 'lead'|'customer' (default: 'lead')
    'contact_status',     // 'new'|'qualified'|'active'|'inactive' (default: 'new')
    'source',             // string (e.g., 'website_form', 'event', 'referral')
    'notes'               // string
];
```

---

### ❓ **Q: How do I handle duplicate email errors?**

**A:** The API returns HTTP 409 for duplicate emails.

**Error Response:**
```json
{
  "error": "Contact with this email already exists",
  "code": 409
}
```

**Handling:**
```php
if ($httpCode === 409) {
    // Contact already exists - you might want to update instead
    return ['success' => false, 'error' => 'Contact already exists'];
}
```

**Prevention:**
```php
// Check if contact exists before creating
$checkUrl = CRM_BASE_URL . 'contacts?email=' . urlencode($email);
$existing = json_decode(file_get_contents($checkUrl), true);
if (!empty($existing['contacts'])) {
    // Contact already exists
}
```

---

### ❓ **Q: What's the best way to debug API issues?**

**A:** Use comprehensive logging and testing:

**1. Enable Detailed Logging:**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Log all API interactions
error_log("CRM API Request - URL: $url");
error_log("CRM API Data: " . json_encode($data));
error_log("CRM API HTTP Code: " . $httpCode);
error_log("CRM API Response: " . $response);
```

**2. Test with Minimal Data:**
```php
// Start with just required fields
$minimalData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test-' . time() . '@example.com'
];
```

**3. Use Standalone Test Scripts:**
```php
// Create separate test files to isolate issues
// test/crm_test.php - for debugging specific problems
```

---

### ❓ **Q: How do I handle rate limiting?**

**A:** The API may have rate limits. Implement proper error handling:

**Rate Limit Response:**
```json
{
  "error": "Rate limit exceeded",
  "code": 429
}
```

**Handling:**
```php
if ($httpCode === 429) {
    // Wait before retrying
    sleep(5);
    // Implement exponential backoff for retries
}
```

**Best Practices:**
- Implement reasonable delays between requests
- Use exponential backoff for retries
- Cache responses when possible
- Monitor your request frequency

---

### ❓ **Q: What's the difference between the guide examples and actual API behavior?**

**A:** The API has some quirks not mentioned in the documentation:

**Guide vs Reality:**
| Guide Says | Actual Behavior |
|------------|-----------------|
| Returns contact directly | Returns `{"success":true,"contact":{...}}` |
| Clean JSON responses | May concatenate error responses before success |
| HTTP 200 for success | HTTP 201 for contact creation |
| Simple error format | Complex malformed JSON responses |

**Always handle both cases:**
```php
// Handle both clean and malformed responses
if (strpos($response, '{"success":true') !== false) {
    // Extract success response from malformed JSON
    $successStart = strpos($response, '{"success":true');
    $cleanResponse = substr($response, $successStart);
} else {
    $cleanResponse = $response;
}
```

---

## 🛠 **Complete Working Example**

Here's a complete, tested implementation:

```php
function createCrmContact($contactData) {
    try {
        $url = 'https://crm.freeopsdao.com/api/v1/contacts';
        
        // Validate required fields
        if (empty($contactData['first_name']) || empty($contactData['last_name']) || empty($contactData['email'])) {
            return ['success' => false, 'error' => 'Missing required fields'];
        }
        
        // Prepare data
        $data = [
            'first_name' => trim($contactData['first_name']),
            'last_name' => trim($contactData['last_name']),
            'email' => trim($contactData['email']),
            'source' => 'website_form'
        ];
        
        // Add optional fields
        if (!empty($contactData['phone'])) $data['phone'] = trim($contactData['phone']);
        if (!empty($contactData['company'])) $data['company'] = trim($contactData['company']);
        if (!empty($contactData['notes'])) $data['notes'] = trim($contactData['notes']);
        
        // Make API request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . CRM_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For local testing
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'Network error: ' . $error];
        }
        
        // Handle malformed JSON response
        $cleanResponse = $response;
        if (strpos($response, '{"success":true') !== false) {
            $successStart = strpos($response, '{"success":true');
            $cleanResponse = substr($response, $successStart);
        }
        
        $result = json_decode($cleanResponse, true);
        if ($result === null) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }
        
        if ($httpCode === 201) {
            if (isset($result['success']) && $result['success'] === true && isset($result['contact'])) {
                return ['success' => true, 'data' => $result['contact']];
            } else {
                return ['success' => false, 'error' => 'Unexpected response structure'];
            }
        } else {
            $errorMsg = 'API Error (HTTP ' . $httpCode . ')';
            if (isset($result['error'])) $errorMsg .= ': ' . $result['error'];
            return ['success' => false, 'error' => $errorMsg];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}
```

---

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
| 1.1.0 | 2025-01-18 | Added comprehensive FAQ with real troubleshooting findings |

---

**Last Updated**: January 18, 2025  
**API Version**: v1  
**Maintainer**: FreeOpsDAO Development Team 