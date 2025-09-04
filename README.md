# Sanctum CRM

A modern Customer Relationship Management system built with PHP, Bootstrap UI, and SQLite, designed for managing contacts, deals, and business relationships with Web3 integration capabilities.

## 📄 License

This project is distributed under a **dual license** structure:

- **Software Code** (PHP, JavaScript, CSS, HTML, SQL, etc.): [GNU Affero General Public License v3.0](LICENSE-AGPLv3)
- **Documentation & Content** (README, docs, images, etc.): [Creative Commons Attribution-ShareAlike 4.0](LICENSE-CC-BY-SA)

See [LICENSE](LICENSE) for complete details.

## 🚀 Features

- **Contact Management**: Unified leads and customers with Web3 integration
- **Deal Pipeline**: Track sales opportunities and conversions with Kanban view
- **User Management**: Admin interface for user management and API key generation
- **Reports & Analytics**: Comprehensive sales analytics with charts and exports
- **Webhook System**: Real-time notifications and integrations
- **Settings Management**: User profile and password management
- **MCP-Ready API**: Machine-friendly endpoints for AI agents and automation
- **Modern UI**: Responsive Bootstrap 5 interface with interactive components
- **Web3 Integration**: EVM addresses, social media handles, and blockchain-ready
- **API-First Design**: RESTful API with OpenAPI documentation

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
git clone https://github.com/actuallyrizzn/bestjobsinta.com.git
cd bestjobsinta.com
```

### 2. Set Up Development Environment
```bash
# Start PHP built-in server from the public directory
cd public
php -S localhost:8000
```

### 3. Access the Application
- Open your browser to `http://localhost:8000`
- Default admin credentials: `admin/admin123`

### 4. Run Tests
```bash
# Run all tests
php tests/run_tests.php

# Run specific test suites
php tests/unit/DatabaseTest.php
php tests/unit/AuthTest.php
php tests/api/ApiTest.php

# Web interface for tests
# Visit http://localhost:8000/tests/run_tests.php
```

## 📁 Project Structure

```
bestjobsinta.com/
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

## 🔒 Deployment Best Practices
- Set your web server root to `/public` (not the project root)
- Never expose `/includes`, `/db`, `/tests`, or `/docs` to the web
- All sensitive files are outside the web root for security

## 🔌 API Usage

### Authentication
```bash
# Using Bearer token (preferred)
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://your-domain.com/api/v1/contacts

# Using query parameter
curl https://your-domain.com/api/v1/contacts?api_key=YOUR_API_KEY
```

### Example API Calls
```bash
# Get all contacts
GET /api/v1/contacts

# Create new contact
POST /api/v1/contacts
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "evm_address": "0x1234...",
  "twitter_handle": "@johndoe"
}

# Convert lead to customer
PUT /api/v1/contacts/123/convert
```

## 🔧 Configuration

Edit `includes/config.php` to customize:
- Database settings
- Application configuration
- Security settings
- API settings

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

- [x] Webhook system implementation
- [x] Advanced reporting and analytics
- [x] User management interface
- [x] Settings management
- [ ] Email integration
- [ ] Calendar integration
- [ ] Mobile app
- [ ] Advanced MCP features
- [ ] Batch operations
- [ ] Advanced filtering & search

---

**Version**: 1.0.0  
**Last Updated**: 2025  
**License**: CC-BY-SA 