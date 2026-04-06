# Bommer BOM Management System - Full Stack Application

Complete full-stack BOM (Bill of Materials) management system with secure authentication, role-based access control, and comprehensive CRUD operations.

## 📋 Features

### Backend
- ✅ **Complete REST API** for BOMs, Projects, Assemblies, Components
- ✅ **Secure Authentication** with session management, CSRF protection, brute-force protection
- ✅ **Role-Based Access Control** (Admin/User roles)
- ✅ **Audit Logging** for all system activities
- ✅ **Password Hashing** with bcrypt
- ✅ **Remember Me** functionality with secure tokens
- ✅ **SQL Injection Protection** via PDO prepared statements

### Frontend
- ✅ **Modern Dark Theme** UI with Clarity Design System
- ✅ **Responsive Layout** optimized for desktop and tablet
- ✅ **Real-time API Integration** with backend
- ✅ **Dashboard** with statistics and recent activity
- ✅ **BOM Management** (Create, View, Edit, Compare, Matrix views)
- ✅ **Project Management** with BOM associations
- ✅ **Assembly Management** with multi-project support
- ✅ **Component Library** with where-used analysis
- ✅ **Global Search** across BOMs, Projects, and Components
- ✅ **Audit Log** viewer

### Database
- ✅ **Comprehensive Schema** for complete BOM lifecycle
- ✅ **Revision Control** for BOMs with status tracking
- ✅ **Component Catalog** with inventory management
- ✅ **Audit Trail** with full change history

## 🚀 Quick Start

### Prerequisites
- WAMP Server (or LAMP/MAMP)
- PHP 7.4+ (tested with PHP 8.3.6)
- MySQL 5.7+ (tested with MySQL 8.3.0)
- Apache with mod_rewrite

### Installation

1. **Run Complete Setup Script**
   ```bash
   setup-complete.bat
   ```
   This will:
   - Create the `bommer_auth` database
   - Import authentication schema
   - Import BOM management schema
   - Create sample data

2. **Access the Application**
   ```
   http://bommer.local/
   ```

3. **Login with Default Credentials**
   - Username: `admin`
   - Password: `Admin@123`
   
   ⚠️ **Change this password immediately after first login!**

## 📁 Project Structure

```
bommer/
├── api/                          # Backend API endpoints
│   ├── index.php                 # API base configuration
│   ├── boms.php                  # BOM CRUD operations
│   ├── projects.php              # Project CRUD operations
│   ├── products.php              # Product CRUD operations
│   ├── components.php            # Component CRUD operations
│   └── audit.php                 # Audit log endpoint
├── auth/                         # Authentication system
│   ├── login.php                 # Login page
│   ├── logout.php                # Logout handler
│   └── validate-login.php        # Login validation
├── admin/                        # Admin panel
│   └── admin-users.php           # User management
├── config/                       # Configuration
│   └── database.php              # Database configuration
├── database/                     # Database schemas
│   ├── schema.sql                # Authentication schema
│   └── bommer-schema.sql         # BOM management schema
├── includes/                     # Shared PHP includes
│   ├── session.php               # Session management
│   └── functions.php             # Security and helper functions
├── public/                       # Public assets
│   ├── css/                      # Stylesheets
│   ├── js/                       # JavaScript files
│   │   └── api.js                # API client service
│   ├── fonts/                    # Local fonts
│   └── node_modules/             # Clarity Design System
├── app.html                      # Main application shell
├── app-router.js                 # Frontend router with API integration
├── index.php                     # Main entry point
├── setup-complete.bat            # Complete setup script
└── README-FULLSTACK.md           # This file
```

## 🔐 Security Features

### Authentication & Authorization
- Session fingerprinting (User-Agent + IP prefix)
- Session regeneration on login and periodically
- CSRF token protection on all forms
- Brute-force protection (5 attempts = 15 min lock)
- Secure password hashing with bcrypt
- Remember-me with selector/validator pattern

### API Security
- Authentication required for all API endpoints
- Role-based access control
- JSON-only input/output
- Error logging without information disclosure
- SQL injection prevention via PDO

### Data Protection
- No physical deletes (soft delete via status)
- Complete audit trail
- Foreign key constraints
- UTF-8 charset (utf8mb4) throughout

## 📊 Database Schema

### Core Tables
- **users** - User accounts with authentication
- **projects** - Project management
- **products** - Product definitions
- **product_projects** - Many-to-many relationship
- **components** - Component catalog
- **boms** - BOM headers with SKU
- **bom_revisions** - BOM version control
- **bom_groups** - BOM group organization
- **bom_items** - BOM line items
- **audit_logs** - Complete activity history
- **remember_tokens** - Secure remember-me tokens

## 🎯 API Endpoints

### BOMs
- `GET /api/boms.php` - List all BOMs (with filters)
- `GET /api/boms.php?id={id}` - Get BOM details
- `POST /api/boms.php` - Create new BOM
- `PUT /api/boms.php` - Update BOM
- `DELETE /api/boms.php?id={id}` - Delete BOM (soft delete)

### Projects
- `GET /api/projects.php` - List all projects
- `GET /api/projects.php?id={id}` - Get project details
- `POST /api/projects.php` - Create project
- `PUT /api/projects.php` - Update project
- `DELETE /api/projects.php?id={id}` - Delete project

### Assemblies
- `GET /api/assemblies.php` - List all assemblies
- `GET /api/assemblies.php?id={id}` - Get assembly details
- `POST /api/assemblies.php` - Create assembly
- `PUT /api/assemblies.php` - Update assembly
- `DELETE /api/assemblies.php?id={id}` - Delete assembly

### Components
- `GET /api/components.php` - List all components
- `GET /api/components.php?id={id}` - Get component details (with where-used)
- `POST /api/components.php` - Create component
- `PUT /api/components.php` - Update component
- `DELETE /api/components.php?id={id}` - Delete component

### Audit Logs
- `GET /api/audit.php` - List audit logs (with filters)

## 🔧 Configuration

### Database Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bommer_auth');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your password
```

### Base URL
If not using `/bommer/` as the base path, update:
- `index.php` - redirect paths
- `public/js/api.js` - API base URL
- `app.html` - resource paths

## 📱 User Interface

### Main Views
1. **Dashboard** - System overview with statistics and recent activity
2. **BOMs** - List, detail, create, compare, and matrix views
3. **Projects** - Project management with BOM associations
4. **Assemblies** - Assembly management with multi-project support
5. **Components** - Component library with where-used analysis
6. **Users** - User management (admin only)
7. **Audit Log** - Complete activity history
8. **Account** - User settings and profile

### Special Views
- **BOM Creation** - Full-featured BOM editor (iframe integration)
- **BOM Comparison** - Side-by-side comparison up to 5 BOMs
- **Matrix View** - Configuration matrix for project/assembly analysis

## 🛠️ Development

### Adding New API Endpoints
1. Create new PHP file in `api/` directory
2. Include `api/index.php` for base configuration
3. Use provided helper functions (sendJson, sendError, etc.)
4. Add corresponding methods to `public/js/api.js`

### Adding New Frontend Pages
1. Add route handler in `app-router.js` (navigateTo method)
2. Add render function in Pages object
3. Use API service for data loading
4. Follow existing patterns for consistency

## 🐛 Troubleshooting

### Database Connection Failed
- Check WAMP is running (green icon)
- Verify credentials in `config/database.php`
- Ensure `bommer_auth` database exists

### API Returns 401 Unauthorized
- Check session is active (login again)
- Clear browser cookies
- Check session permissions

### BOMs/Data Not Loading
- Check browser console for errors
- Verify API endpoints are accessible
- Check database has sample data

### Fonts Not Loading
- Run `npm install` in `/public/` directory
- Verify Clarity Design System is installed
- Check font file paths in CSS

## 📝 Sample Data

The setup script includes sample data:
- 3 Projects (Alpha, Beta, Gamma)
- 3 Assemblies (Main Board, Power Supply, Enclosure)
- 5 Components (Resistors, Capacitors, ICs, Connectors, LEDs)
- 3 BOMs with multiple groups and items
- 1 Admin user (admin/Admin@123)

## 🔄 Next Steps

1. Change default admin password
2. Create additional user accounts
3. Configure project-specific settings
4. Import real component data
5. Create your first BOM
6. Set up automated backups
7. Review and customize permissions

## 📄 Documentation

- [AUTH_README.md](AUTH_README.md) - Authentication system details
- [FRONTEND_README.md](FRONTEND_README.md) - Frontend architecture
- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [QUICKSTART.md](QUICKSTART.md) - Quick start guide

## 🆘 Support

For issues or questions:
1. Check troubleshooting section above
2. Review documentation files
3. Check browser console and PHP error logs
4. Verify database schema is complete

## 📜 License

Internal use for Bommer project.

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Status**: Production Ready ✅
