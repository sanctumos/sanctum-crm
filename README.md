# Sanctum CRM

A modern, API-first Customer Relationship Management system built with PHP, Bootstrap UI, and SQLite, designed for seamless integration with Letta AI agents and the broader Sanctum AI ecosystem. Features intelligent first-boot configuration and MCP (Model Context Protocol) compatibility for AI agent integration.

## 📄 License

This project is distributed under a **dual license** structure:

- **Software Code** (PHP, JavaScript, CSS, HTML, SQL, etc.): [GNU Affero General Public License v3.0](LICENSE-AGPLv3)
- **Documentation & Content** (README, docs, images, etc.): [Creative Commons Attribution-ShareAlike 4.0](LICENSE-CC-BY-SA)

See [LICENSE](LICENSE) for complete details.

## 🚀 Features

- **🤖 AI Agent Ready**: MCP-compatible API designed for Letta AI and other agentic AI systems
- **⚡ First Boot Configuration**: Intelligent setup wizard for instant deployment
- **👥 Contact Management**: Unified leads and customers with comprehensive data fields
- **🔀 Contact Merges**: Confidence-tiered duplicate detection with inspect/accept workflow
- **🏷️ Contact Tags & Sidecar**: Many-to-many tags plus durable enrichment facts storage
- **✨ Enrichment Automation**: Scheduled RocketReach enrichment with per-run and daily caps
- **🎨 Skin Lab**: Theme presets (hey / ledger / brutalist / obsidian) aligned with Sanctum Tasks
- **📬 Webhook Delivery Queue**: Durable outbound webhook processing
- **💼 Deal Pipeline**: Track sales opportunities and conversions with Kanban view
- **👤 User Management**: Admin interface for user management and API key generation
- **📊 Reports & Analytics**: Comprehensive sales analytics with charts and exports
- **🔗 Webhook System**: Real-time notifications and integrations
- **⚙️ Dynamic Configuration**: Database-driven settings with encryption support
- **🌐 API-First Design**: RESTful API with OpenAPI documentation
- **📱 Modern UI**: Responsive Bootstrap 5 interface with interactive components
- **🔒 Enterprise Security**: SQLite with fixed paths, input validation, and session security

## 🛠 Technology Stack

- **Backend**: PHP 8.0+
- **Frontend**: Bootstrap 5.x
- **Database**: SQLite 3
- **API**: RESTful with JSON responses
- **Authentication**: Session-based (web) + API keys (programmatic)

## 📋 Requirements

- PHP 8.0 or higher
- SQLite3 extension enabled
- Web server (Apache/Nginx) or PHP built-in server
- Modern web browser

## 🚀 Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/sanctumos/sanctum-crm.git
cd sanctum-crm
```

### 2. First Boot Setup
```bash
# Start PHP built-in server from the public directory
cd public
php -S localhost:8000
```

### 3. Complete Installation Wizard
- Open your browser to `http://localhost:8000`
- Follow the 5-step installation wizard:
  1. **Environment Check**: Verify PHP and SQLite requirements
  2. **Database Setup**: Initialize SQLite database with proper schema
  3. **Company Info**: Configure your company name and basic settings
  4. **Admin User**: Create your administrator account
  5. **Finalization**: Complete setup and access your CRM

### 4. AI Agent Integration
```bash
# Get your API key from the admin panel
# Use with Letta AI or any MCP-compatible agent
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-domain.com/api/v1/contacts
```

### 5. Run Tests
```bash
# Run comprehensive test suite (100% coverage)
php tests/run_tests.php

# Run specific test suites
php tests/unit/ConfigManagerCustomTest.php
php tests/integration/FirstBootIntegrationTest.php
php tests/e2e/InstallationWizardE2ETest.php
```

## 📁 Project Structure

```
sanctum-crm/
├── public/                  # Web root (all public files)
│   ├── index.php           # Main entry point with first-boot detection
│   ├── install.php         # Installation wizard interface
│   ├── login.php           # Authentication
│   ├── logout.php          # Session cleanup
│   ├── api/
│   │   └── v1/
│   │       └── index.php   # MCP-compatible API endpoints
│   ├── pages/              # Web interface pages
│   │   ├── dashboard.php
│   │   ├── contacts.php
│   │   ├── deals.php
│   │   ├── settings.php    # Dynamic configuration management
│   │   └── ...
│   └── includes/           # Shared components
│       ├── config.php
│       ├── database.php
│       ├── ConfigManager.php      # Dynamic configuration system
│       ├── InstallationManager.php # First-boot setup
│       ├── EnvironmentDetector.php # Server environment analysis
│       └── layout.php
├── db/                      # SQLite database (private)
│   └── crm.db
├── tests/                   # Comprehensive test suite (100% coverage)
│   ├── unit/               # Unit tests
│   ├── integration/        # Integration tests
│   ├── e2e/               # End-to-end tests
│   └── api/               # API tests
├── docs/                   # Documentation
└── README.md
```

## 🔒 Deployment Best Practices
- Set your web server root to `/public` (not the project root)
- Never expose `/includes`, `/db`, `/tests`, or `/docs` to the web
- All sensitive files are outside the web root for security

## 🤖 AI Agent Integration

### MCP (Model Context Protocol) Compatibility
Sanctum CRM is designed for seamless integration with Letta AI and other agentic AI systems through MCP-compatible APIs.

### Authentication
```bash
# Using Bearer token (preferred for AI agents)
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-domain.com/api/v1/contacts

# Using query parameter (fallback)
curl https://your-domain.com/api/v1/contacts?api_key=YOUR_API_KEY
```

### Example AI Agent Usage
```bash
# Get all contacts (AI agent can analyze customer data)
GET /api/v1/contacts

# Create new contact (AI agent can add leads from conversations)
POST /api/v1/contacts
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "company": "Acme Corp",
  "phone": "+1234567890",
  "source": "ai_agent_conversation"
}

# Update contact status (AI agent can track interactions)
PUT /api/v1/contacts/123
{
  "contact_status": "qualified",
  "notes": "AI agent identified high-value prospect"
}

# Get configuration (AI agent can understand system settings)
GET /api/v1/settings
```

### Letta AI Integration Example
```javascript
// Example MCP tool for Letta AI
const crmTool = {
  name: "sanctum_crm",
  description: "Customer Relationship Management system",
  parameters: {
    action: {
      type: "string",
      enum: ["create_contact", "update_contact", "get_contacts", "create_deal"],
      description: "Action to perform"
    },
    contact_data: {
      type: "object",
      description: "Contact information"
    }
  }
};
```

## ⚙️ Configuration

### First Boot Configuration
The system automatically detects first-time installation and guides you through:
1. **Environment Validation**: Checks PHP version, SQLite support, and server configuration
2. **Database Initialization**: Creates SQLite database with proper schema
3. **Company Setup**: Configure your company name and basic information
4. **Admin Account**: Create your administrator user account
5. **Finalization**: Complete setup and access your CRM

### Dynamic Configuration Management
After installation, use the admin settings panel to manage:
- **Company Information**: Update company name and details
- **System Settings**: Configure application behavior
- **User Management**: Add/remove users and manage API keys
- **Environment Detection**: View server environment analysis

### API Configuration
- **API Keys**: Generate and manage API keys for AI agent integration
- **Rate Limiting**: Configure request limits and throttling
- **Webhook Settings**: Set up real-time notifications
- **Security Settings**: Configure authentication and access controls

## 📚 Documentation

- **[📖 Comprehensive Documentation](docs/COMPREHENSIVE_DOCUMENTATION.md)** - Complete system documentation including API reference, integration guides, deployment instructions, and troubleshooting
- [OpenAPI Specification](public/api/openapi.json) - Machine-readable API specification

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the CC-BY-SA License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation in the `docs/` folder
- Contact the development team

## 🔮 Roadmap

### ✅ Completed (v2.0.0)
- [x] First boot configuration system
- [x] Dynamic configuration management
- [x] MCP-compatible API design
- [x] Comprehensive test suite (100% coverage)
- [x] Environment detection and validation
- [x] Database-driven settings with encryption
- [x] Webhook system implementation
- [x] Advanced reporting and analytics
- [x] User management interface

### 🚀 Near Term (v2.1.0 - v2.3.0)
- [ ] **Activity Feed System** - Comprehensive activity tracking for contact interactions with chronological feed, memo support, and filtering capabilities (see [Feature Planning](docs/FEATURE_PLANNING_ACTIVITY_FEED.md) for details)
- [ ] **Sanctum Agent Direct Integration** - Built-in chatbot interface for direct AI agent interaction within the CRM
- [ ] **MCP Direct Compatibility** - Enhanced Model Context Protocol integration for seamless Letta AI and other agentic system connectivity

### 🔮 Future (v2.4.0+)
- [ ] Advanced MCP features for Letta AI
- [ ] Batch operations and bulk imports
- [ ] Advanced filtering & search
- [ ] Real-time collaboration features
- [ ] Mobile app with offline support

---

**Version**: 2.0.0  
**Last Updated**: January 2025  
**License**: Dual (AGPLv3 + CC-BY-SA)  
**Compatibility**: Letta AI, MCP Protocol, PHP 8.0+ 