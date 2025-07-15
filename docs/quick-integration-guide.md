# FreeOpsDAO CRM - API Integration Guide

## 🚀 Quick Start

This guide provides ready-to-use code snippets for integrating with the FreeOpsDAO CRM API.

## 📋 Prerequisites

1. **API Key**: Contact the CRM administrator for your API key
2. **Base URL**: `https://crm.freeopsdao.com/api/v1/`
3. **Authentication**: Use Bearer token in Authorization header

## 🔑 Authentication

All API requests require authentication using your API key with **Bearer token format**:

```javascript
const headers = {
  'Content-Type': 'application/json',
  'Authorization': `Bearer YOUR_API_KEY`
};
```

### ⚠️ Common Authentication Error

**IMPORTANT**: Always use the `Bearer ` prefix before your API key. This is a common mistake that causes 401 Unauthorized errors.

❌ **Wrong:**
```javascript
'Authorization': 'YOUR_API_KEY'
```

✅ **Correct:**
```javascript
'Authorization': 'Bearer YOUR_API_KEY'
```

**Error you'll see if wrong:**
```
HTTP/1.1 401 Unauthorized
```

## 📝 Common Integration Examples

### 1. Contact Form Integration

```javascript
const CRM_API_KEY = 'your_api_key_here';
const CRM_BASE_URL = 'https://crm.freeopsdao.com/api/v1';

async function createContact(contactData) {
  const response = await fetch(`${CRM_BASE_URL}/contacts`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${CRM_API_KEY}`
    },
    body: JSON.stringify(contactData)
  });
  
  if (response.ok) {
    return await response.json();
  } else {
    const error = await response.json();
    throw new Error(error.error || 'API Error');
  }
}

// Example usage
const newContact = {
  first_name: 'John',
  last_name: 'Doe',
  email: 'john.doe@example.com',
  company: 'Acme Corp',
  phone: '+1234567890',
  source: 'website_form'
};

createContact(newContact)
  .then(contact => console.log('Contact created:', contact.id))
  .catch(error => console.error('Error:', error));
```

### 2. WordPress Integration

```php
// Add to functions.php
function create_crm_contact($contact_data) {
    $response = wp_remote_post('https://crm.freeopsdao.com/api/v1/contacts', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . CRM_API_KEY
        ),
        'body' => json_encode($contact_data)
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    return json_decode(wp_remote_retrieve_body($response), true);
}

// Hook into form submission
add_action('wp_ajax_contact_form', 'handle_contact_form');
function handle_contact_form() {
    $crm_data = array(
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'email' => sanitize_email($_POST['email']),
        'source' => 'wordpress_form'
    );
    
    $result = create_crm_contact($crm_data);
    wp_die();
}
```

### 3. React Component

```jsx
import React, { useState } from 'react';

const ContactForm = () => {
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    company: ''
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const contactData = {
      first_name: formData.firstName,
      last_name: formData.lastName,
      email: formData.email,
      company: formData.company,
      source: 'react_form'
    };
    
    try {
      const response = await fetch('https://crm.freeopsdao.com/api/v1/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${process.env.REACT_APP_CRM_API_KEY}`
        },
        body: JSON.stringify(contactData)
      });
      
      if (response.ok) {
        alert('Contact created successfully!');
        setFormData({ firstName: '', lastName: '', email: '', company: '' });
      } else {
        const error = await response.json();
        alert('Error: ' + error.error);
      }
    } catch (error) {
      alert('Network error. Please try again.');
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="text"
        placeholder="First Name"
        value={formData.firstName}
        onChange={(e) => setFormData({...formData, firstName: e.target.value})}
        required
      />
      <input
        type="text"
        placeholder="Last Name"
        value={formData.lastName}
        onChange={(e) => setFormData({...formData, lastName: e.target.value})}
        required
      />
      <input
        type="email"
        placeholder="Email"
        value={formData.email}
        onChange={(e) => setFormData({...formData, email: e.target.value})}
        required
      />
      <input
        type="text"
        placeholder="Company"
        value={formData.company}
        onChange={(e) => setFormData({...formData, company: e.target.value})}
      />
      <button type="submit">Submit</button>
    </form>
  );
};
```

### 4. E-commerce Webhook

```javascript
// Shopify webhook handler
app.post('/webhooks/customer/create', async (req, res) => {
  const customer = req.body;
  
  const crmData = {
    first_name: customer.first_name,
    last_name: customer.last_name,
    email: customer.email,
    phone: customer.phone,
    company: customer.company,
    contact_type: 'customer',
    contact_status: 'active',
    source: 'shopify_customer',
    notes: `Customer ID: ${customer.id}`
  };
  
  try {
    await fetch('https://crm.freeopsdao.com/api/v1/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.CRM_API_KEY}`
      },
      body: JSON.stringify(crmData)
    });
  } catch (error) {
    console.error('CRM integration failed:', error);
  }
  
  res.status(200).send('OK');
});
```

### 5. Discord Bot Integration

```javascript
// Discord.js bot
client.on('guildMemberAdd', async (member) => {
  const crmData = {
    first_name: member.user.username,
    last_name: '',
    email: member.user.email || `${member.user.username}@discord.com`,
    discord_username: member.user.tag,
    contact_type: 'lead',
    source: 'discord_member',
    notes: `Joined Discord server: ${member.guild.name}`
  };
  
  try {
    await fetch('https://crm.freeopsdao.com/api/v1/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.CRM_API_KEY}`
      },
      body: JSON.stringify(crmData)
    });
  } catch (error) {
    console.error('Failed to create CRM contact:', error);
  }
});
```

## 🔧 Testing Your Integration

### Quick Test Script

```javascript
async function testCrmIntegration() {
  const testContact = {
    first_name: 'Test',
    last_name: 'User',
    email: `test-${Date.now()}@example.com`,
    source: 'integration_test'
  };
  
  try {
    const response = await fetch('https://crm.freeopsdao.com/api/v1/contacts', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${process.env.CRM_API_KEY}`
      },
      body: JSON.stringify(testContact)
    });
    
    if (response.ok) {
      const result = await response.json();
      console.log('✅ Integration successful! Contact ID:', result.id);
    } else {
      const error = await response.json();
      console.error('❌ Integration failed:', error);
    }
  } catch (error) {
    console.error('❌ Network error:', error);
  }
}

testCrmIntegration();
```

### cURL Test

```bash
curl -X POST https://crm.freeopsdao.com/api/v1/contacts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "source": "curl_test"
  }'
```

## 📊 Available Endpoints

### Contacts
- `POST /contacts` - Create new contact
- `GET /contacts` - List contacts
- `GET /contacts/{id}` - Get specific contact
- `PUT /contacts/{id}` - Update contact
- `DELETE /contacts/{id}` - Delete contact
- `POST /contacts/{id}/convert` - Convert lead to customer

### Deals
- `POST /deals` - Create new deal
- `GET /deals` - List deals
- `GET /deals/{id}` - Get specific deal
- `PUT /deals/{id}` - Update deal
- `DELETE /deals/{id}` - Delete deal

### Users (Admin Only)
- `GET /users` - List users
- `POST /users` - Create user
- `GET /users/{id}` - Get user
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user

### Webhooks
- `POST /webhooks` - Create webhook
- `GET /webhooks` - List webhooks
- `GET /webhooks/{id}` - Get webhook
- `PUT /webhooks/{id}` - Update webhook
- `DELETE /webhooks/{id}` - Delete webhook
- `POST /webhooks/{id}/test` - Test webhook

## 🛠 Troubleshooting

### Common Error Codes

- **401 Unauthorized** - Invalid or missing API key
  - **Most common cause**: Missing `Bearer ` prefix in Authorization header
  - **Fix**: Use `Authorization: Bearer YOUR_API_KEY` instead of `Authorization: YOUR_API_KEY`
- **400 Bad Request** - Missing required fields or invalid data
  - **Most common cause**: Missing `last_name` field
  - **Fix**: Always provide `last_name`, use fallback like "No Last Name Provided" if unknown
- **409 Conflict** - Contact with this email already exists
- **429 Too Many Requests** - Rate limit exceeded
- **500 Server Error** - Internal server error

### Required Fields

For creating contacts, these fields are required:
- `first_name` (string)
- `last_name` (string) 
- `email` (valid email format)

### ⚠️ Common Field Issues

**Last Name is Mandatory**: If you don't have a last name, provide a fallback value:

❌ **Wrong:**
```javascript
{
  first_name: 'John',
  email: 'john@example.com'
  // Missing last_name will cause 400 error
}
```

✅ **Correct:**
```javascript
{
  first_name: 'John',
  last_name: 'No Last Name Provided', // Fallback for missing data
  email: 'john@example.com'
}
```

**Other fallback options:**
- `last_name: 'Unknown'`
- `last_name: 'N/A'`
- `last_name: 'Not Provided'`
- `last_name: 'Anonymous'`

### Rate Limits

- **1000 requests per hour** per API key
- **1MB maximum** request body size

### 🔧 Authentication Troubleshooting

If you're getting 401 Unauthorized errors, check these common issues:

1. **Missing Bearer prefix** (most common):
   ```javascript
   // ❌ Wrong
   headers: { 'Authorization': 'your-api-key-here' }
   
   // ✅ Correct  
   headers: { 'Authorization': 'Bearer your-api-key-here' }
   ```

2. **Invalid API key**:
   - Verify your API key is correct
   - Check for extra spaces or characters
   - Ensure the key hasn't been regenerated

3. **Wrong header name**:
   ```javascript
   // ❌ Wrong
   headers: { 'Auth': 'Bearer your-api-key' }
   
   // ✅ Correct
   headers: { 'Authorization': 'Bearer your-api-key' }
   ```

4. **Case sensitivity**:
   ```javascript
   // ❌ Wrong
   headers: { 'authorization': 'Bearer your-api-key' }
   
   // ✅ Correct
   headers: { 'Authorization': 'Bearer your-api-key' }
   ```

### 🔧 Field Validation Troubleshooting

If you're getting 400 Bad Request errors, check these common field issues:

1. **Missing last_name field** (most common):
   ```javascript
   // ❌ Wrong - will cause 400 error
   const contactData = {
     first_name: 'John',
     email: 'john@example.com'
   };
   
   // ✅ Correct - always include last_name
   const contactData = {
     first_name: 'John',
     last_name: 'No Last Name Provided', // Required field
     email: 'john@example.com'
   };
   ```

2. **Invalid email format**:
   ```javascript
   // ❌ Wrong
   email: 'not-an-email'
   
   // ✅ Correct
   email: 'user@example.com'
   ```

3. **Empty required fields**:
   ```javascript
   // ❌ Wrong
   first_name: '',
   last_name: '',
   email: ''
   
   // ✅ Correct
   first_name: 'John',
   last_name: 'Doe',
   email: 'john@example.com'
   ```

## 📞 Support

- **API Documentation**: See `docs/api-integration-spec.md` for complete API reference
- **Contact**: Reach out to the CRM administrator for API keys and support
- **Status**: Check system status at `https://crm.freeopsdao.com/api/v1/diagnostic`

---

**API Version**: v1.0.0  
**Last Updated**: January 14, 2025 