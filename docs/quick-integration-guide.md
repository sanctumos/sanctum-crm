# Quick Integration Guide - FreeOpsDAO CRM

## 🚀 Quick Start for Developers

This guide provides ready-to-use code snippets for common integration scenarios.

## 📋 Prerequisites

1. **Get API Key**: Contact the CRM administrator for your API key
2. **Base URL**: `https://your-crm-domain.com/api/v1/`
3. **Test First**: Use the examples below to test your integration

## 🎯 Common Integration Scenarios

### 1. Contact Form Integration

#### HTML Form
```html
<form id="crmContactForm">
  <input type="text" name="first_name" placeholder="First Name" required>
  <input type="text" name="last_name" placeholder="Last Name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="text" name="company" placeholder="Company">
  <input type="tel" name="phone" placeholder="Phone">
  <button type="submit">Submit</button>
</form>
```

#### JavaScript Handler
```javascript
const CRM_API_KEY = 'your_api_key_here';
const CRM_BASE_URL = 'https://your-crm-domain.com/api/v1';

document.getElementById('crmContactForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const contactData = {
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    company: formData.get('company'),
    phone: formData.get('phone'),
    source: 'website_form'
  };
  
  try {
    const response = await fetch(`${CRM_BASE_URL}/contacts`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${CRM_API_KEY}`
      },
      body: JSON.stringify(contactData)
    });
    
    if (response.ok) {
      alert('Thank you! We\'ll be in touch soon.');
      e.target.reset();
    } else {
      const error = await response.json();
      alert('Error: ' + (error.error || 'Something went wrong'));
    }
  } catch (error) {
    alert('Network error. Please try again.');
  }
});
```

### 2. Newsletter Signup Integration

#### WordPress Hook Example
```php
// Add to functions.php
add_action('wp_ajax_newsletter_signup', 'handle_newsletter_signup');
add_action('wp_ajax_nopriv_newsletter_signup', 'handle_newsletter_signup');

function handle_newsletter_signup() {
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    
    // Your existing newsletter logic here
    
    // Also create CRM contact
    $crm_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'contact_type' => 'lead',
        'source' => 'newsletter_signup'
    );
    
    $crm_response = wp_remote_post('https://your-crm-domain.com/api/v1/contacts', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . CRM_API_KEY
        ),
        'body' => json_encode($crm_data)
    ));
    
    wp_die();
}
```

### 3. Event Registration Integration

#### React Component Example
```jsx
import React, { useState } from 'react';

const EventRegistration = () => {
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    eventName: 'Blockchain Summit 2025'
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Your existing event registration logic
    const eventResult = await registerForEvent(formData);
    
    // Also create CRM contact
    const crmData = {
      first_name: formData.firstName,
      last_name: formData.lastName,
      email: formData.email,
      source: 'event_registration',
      notes: `Registered for: ${formData.eventName}`
    };
    
    try {
      await fetch('https://your-crm-domain.com/api/v1/contacts', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${process.env.REACT_APP_CRM_API_KEY}`
        },
        body: JSON.stringify(crmData)
      });
    } catch (error) {
      console.error('CRM integration failed:', error);
      // Don't fail the event registration if CRM fails
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Your form fields */}
    </form>
  );
};
```

### 4. E-commerce Integration

#### Shopify Webhook Example
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
    await fetch('https://your-crm-domain.com/api/v1/contacts', {
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

### 5. Social Media Integration

#### Discord Bot Example
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
    await fetch('https://your-crm-domain.com/api/v1/contacts', {
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

### Test Script
```javascript
// test-integration.js
async function testCrmIntegration() {
  const testContact = {
    first_name: 'Test',
    last_name: 'User',
    email: `test-${Date.now()}@example.com`,
    source: 'integration_test'
  };
  
  try {
    const response = await fetch('https://your-crm-domain.com/api/v1/contacts', {
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
curl -X POST https://your-crm-domain.com/api/v1/contacts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "source": "curl_test"
  }'
```

## 🛠 Troubleshooting

### Common Issues

1. **401 Unauthorized**
   - Check your API key is correct
   - Ensure API key is included in Authorization header

2. **400 Bad Request**
   - Verify required fields (first_name, last_name, email)
   - Check email format is valid

3. **409 Conflict**
   - Contact with this email already exists
   - Use GET /contacts?email=... to check first

4. **500 Server Error**
   - Check CRM system is running
   - Contact CRM administrator

### Debug Mode
```javascript
// Enable debug logging
const DEBUG = true;

async function createContact(contactData) {
  if (DEBUG) console.log('Sending contact data:', contactData);
  
  const response = await fetch('https://your-crm-domain.com/api/v1/contacts', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${API_KEY}`
    },
    body: JSON.stringify(contactData)
  });
  
  if (DEBUG) console.log('Response status:', response.status);
  
  if (response.ok) {
    const result = await response.json();
    if (DEBUG) console.log('Success:', result);
    return result;
  } else {
    const error = await response.json();
    if (DEBUG) console.error('Error:', error);
    throw new Error(error.error || 'API Error');
  }
}
```

## 📞 Need Help?

- **Test your integration** with the examples above
- **Check the full API spec** in `docs/api-integration-spec.md`
- **Contact the CRM admin** for API key and support
- **Review error logs** for detailed debugging information

---

**Quick Guide Version**: 1.0.0  
**Last Updated**: January 14, 2025 