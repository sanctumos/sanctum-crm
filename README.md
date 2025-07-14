# FreeOpsDAO CRM System

A modern Customer Relationship Management (CRM) system built with PHP, Bootstrap UI, and SQLite, designed for Web3 and traditional businesses with MCP (Message Control Plane) integration capabilities.

## 🚀 Features

- **Contact Management**: Unified leads and customers with Web3 integration
- **Deal Pipeline**: Track sales opportunities and conversions
- **MCP-Ready API**: Machine-friendly endpoints for AI agents and automation
- **Modern UI**: Responsive Bootstrap 5 interface
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
git clone https://github.com/actuallyrizzn/crm.freeopsdao.com.git
cd crm.freeopsdao.com
```

### 2. Set Up Development Environment
```bash
# Start PHP built-in server
php -S localhost:8000
```

### 3. Access the Application
- Open your browser to `http://localhost:8000`
- Default admin credentials: `admin/admin123`

## 📁 Project Structure

```
crm.freeopsdao.com/
├── assets/          # CSS, JS, images
├── includes/        # PHP includes and functions
├── pages/          # Main application pages
├── api/v1/         # API endpoints
├── db/             # Database files
├── docs/           # Documentation
└── index.php       # Main entry point
```

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

- [Complete System Documentation](docs/basic-crm-system.md)
- [API Reference](docs/api-reference.md) (coming soon)
- [Database Schema](docs/database-schema.md) (coming soon)

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

- [ ] Webhook system implementation
- [ ] Advanced reporting and analytics
- [ ] Email integration
- [ ] Calendar integration
- [ ] Mobile app
- [ ] Advanced MCP features

---

**Version**: 1.0.0  
**Last Updated**: 2025  
**License**: CC-BY-SA 