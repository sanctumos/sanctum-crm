# 🎯 Sanctum Chat Widget - PHP Version

A lightweight, embeddable chat widget for the PHP version of Sanctum Web Chat. This widget provides the exact same functionality as the Flask version, allowing users to embed a chat interface on any website.

## 🚀 Quick Start

### 1. Include the Widget Script
```html
<script src="https://yourdomain.com/widget/assets/js/chat-widget.js"></script>
```

### 2. Initialize the Widget
```html
<script>
  SanctumChat.init({
    apiKey: 'your-api-key',
    position: 'bottom-right',
    theme: 'light'
  });
</script>
```

## 📁 File Structure

```
widget/
├── index.php              # Widget documentation page
├── demo.php               # Interactive demo environment
├── init.php               # Widget initialization endpoint
├── config.php             # Configuration options endpoint
├── health.php             # Health check endpoint
├── embed.php              # Iframe-compatible endpoint
├── test.php               # Test page for verification
├── assets/
│   ├── css/
│   │   └── widget.css     # Widget styles
│   ├── js/
│   │   └── chat-widget.js # Widget JavaScript
│   └── icons/
│       └── chat-icon.svg  # Chat icon
└── templates/
    ├── widget.html        # Documentation template
    └── widget_demo.html   # Demo template
```

## 🔗 Available Endpoints

- **`/widget/`** - Main documentation and embed instructions
- **`/widget/demo`** - Interactive testing environment
- **`/widget/init`** - Widget initialization and configuration
- **`/widget/config`** - Available configuration options
- **`/widget/health`** - Widget health and status
- **`/widget/embed`** - Iframe-compatible embedding
- **`/widget/test`** - Test page for verification

## ⚙️ Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiKey` | string | **required** | Your Sanctum API key |
| `position` | string | `'bottom-right'` | Widget position |
| `theme` | string | `'light'` | Widget theme |
| `title` | string | `'Chat with us'` | Chat window title |
| `primaryColor` | string | `'#007bff'` | Primary brand color |
| `language` | string | `'en'` | Widget language |
| `autoOpen` | boolean | `false` | Auto-open on page load |
| `notifications` | boolean | `true` | Enable notifications |
| `sound` | boolean | `true` | Enable sound alerts |

## 🎨 Features

- **Floating Chat Bubble** - Always-visible chat trigger
- **Expandable Chat Window** - Full chat interface with animations
- **Real-time Messaging** - Live chat with existing PHP API
- **Session Persistence** - Maintains chat state across interactions
- **Responsive Design** - Works perfectly on all devices
- **Theme System** - Light, dark, and auto themes
- **Position Variants** - 8 corner positions with mobile optimization
- **Customization** - Brand colors, titles, and appearance
- **Notifications** - Browser notifications and sound alerts
- **Accessibility** - Full keyboard navigation and screen reader support

## 🔌 API Integration

The widget uses your existing PHP API endpoints:
- **`/api/v1/?action=messages`** - Send messages
- **`/api/v1/?action=inbox`** - Retrieve messages
- **`/api/v1/?action=outbox`** - Send responses
- **`/api/v1/?action=responses`** - Get responses
- **`/api/v1/?action=sessions`** - Session management
- **`/api/v1/?action=config`** - Configuration

**No new API endpoints required** - maintains 100% backward compatibility.

## 🧪 Testing

Visit `/widget/test` to run a comprehensive test of all widget functionality, including:
- Asset loading verification
- Endpoint functionality testing
- Live widget initialization
- Cross-browser compatibility

## 🌐 Browser Support

- **Modern Browsers**: Chrome 60+, Firefox 55+, Safari 12+, Edge 79+
- **Mobile Browsers**: iOS Safari 12+, Chrome Mobile 60+
- **Legacy Support**: IE11+ (with polyfills)

## 🔒 Security

- **API Authentication**: Uses existing PHP API key system
- **Session Isolation**: Each widget instance has unique session IDs
- **Input Sanitization**: All user input is properly sanitized
- **CORS Support**: Configured for cross-origin requests
- **Rate Limiting**: Inherits existing rate limiting

## 📱 Mobile Optimization

- Responsive sizing and positioning
- Touch-friendly interactions
- Mobile-optimized animations
- Adaptive spacing and typography

## 🚀 Performance

- **Lightweight**: ~15KB gzipped JavaScript
- **Fast Loading**: Optimized for quick initialization
- **Efficient Polling**: Smart API polling with exponential backoff
- **Memory Management**: Proper cleanup and event handling

## 🔧 Advanced Usage

### Programmatic Control
```javascript
// Open/close chat
SanctumChat.open();
SanctumChat.close();
SanctumChat.toggle();

// Send message programmatically
SanctumChat.sendMessage('Hello from code!');

// Update configuration
SanctumChat.updateConfig({
  theme: 'dark',
  title: 'New Chat Title'
});
```

### Event Listeners
```javascript
// Listen for widget events
SanctumChat.on('open', function() {
  console.log('Chat opened');
});

SanctumChat.on('message', function(data) {
  console.log('New message:', data.message);
});

SanctumChat.on('close', function() {
  console.log('Chat closed');
});

SanctumChat.on('error', function(error) {
  console.error('Widget error:', error);
});
```

## 🎯 Integration Examples

### WordPress Integration
```php
<script>
document.addEventListener('DOMContentLoaded', function() {
  SanctumChat.init({
    apiKey: '<?php echo get_option("sanctum_api_key"); ?>',
    position: 'bottom-right',
    title: '<?php echo get_option("sanctum_chat_title", "Chat with us"); ?>'
  });
});
</script>
```

### Shopify Integration
```html
<script>
SanctumChat.init({
  apiKey: 'your-shopify-api-key',
  position: 'bottom-right',
  primaryColor: '#007bff'
});
</script>
```

## 🛡️ Security & Privacy

- **Secure by Design**: Uses existing API authentication system
- **No Cross-site Data Leakage**: Proper session isolation
- **GDPR Compliant**: Full privacy protection
- **Input Validation**: All user input validated and sanitized

## 📞 Support

- **Documentation**: `/widget/` - Complete widget documentation
- **Demo**: `/widget/demo` - Interactive testing environment
- **Health**: `/widget/health` - Widget status check
- **Test**: `/widget/test` - Comprehensive testing page

## 📄 License

This widget is part of the Sanctum Chat system and follows the same licensing terms.

---

**Built with ❤️ for seamless customer communication**

*Feature parity: 100% identical to Flask version*
