# 📋 InvoicePro — Professional Invoice Management System

A **full-stack, production-ready** invoice management system built with PHP/MySQL backend and vanilla JavaScript frontend. Designed for freelancers, small businesses, and agencies to create, manage, and track invoices professionally.

---

## ✨ Key Features

### 📊 Dashboard
- Real-time business overview with KPI cards (Total Invoices, Revenue, Pending, Clients)
- Interactive revenue chart (6 months / 1 year view) with Chart.js
- Invoice status distribution doughnut chart
- Recent invoices at a glance

### 📄 Invoice Management
- Create, edit, and delete invoices with line items
- Auto-calculated subtotal, tax, and grand total
- Status tracking: Draft → Sent → Partial → Paid / Overdue
- **Invoice Preview** with professional paper layout
- **Print-to-PDF** support via browser print
- **CSV Export** for accounting software integration
- Client snapshot for historical accuracy on invoices

### 👥 Client Management
- Add, edit, and archive (soft-delete) clients
- Client card view with contact details
- Financial summary per client (billed, paid, outstanding)
- Search and filter clients
- GST/Tax number tracking

### 💳 Payment Tracking
- Record payments against invoices
- Multiple payment methods (Bank Transfer, UPI, Cash, Cheque, Card)
- Payment reference tracking
- Auto-update invoice status on payment

### 📈 Reports & Analytics
- Revenue trend line chart
- Client distribution analysis
- Collection rate percentage
- Time-period filters (30 days, 90 days, 1 year, all time)

### ⚙️ Settings
- Business profile configuration (name, address, GST)
- Logo upload support
- Default tax rate configuration
- Invoice numbering format customization
- Payment terms template

### 🎨 Premium UI/UX
- **Dark Mode** with smooth toggle
- Responsive design (desktop, tablet, mobile)
- Glassmorphism login page with animated background
- Toast notifications for all actions
- Confirmation dialogs for destructive actions
- Keyboard shortcuts (Escape to close modals)
- SPA architecture with hash-based routing

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3 (Custom Properties), Vanilla JavaScript |
| Backend | PHP 7.4+ (OOP, PDO) |
| Database | MySQL 8.0 |
| Charts | Chart.js 4.x |
| Icons | Font Awesome 6.x |
| Typography | Google Fonts (Inter) |

---

## 🚀 Installation

### Prerequisites
- **XAMPP** (or any Apache + PHP + MySQL stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Steps

1. **Copy the project** to your web server directory:
   ```
   C:\xampp\htdocs\invoice-management\
   ```

2. **Import the database schema:**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database: `invoice_management`
   - Import `schema.sql` from the project root

3. **Configure database** (if needed):
   Edit `backend/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'invoice_management');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Access the application:**
   ```
   http://localhost/invoice-management/frontend/login.html
   ```

5. **Default login:**
   - Email: `test@example.com`
   - Password: any password (development mode)

---

## 📁 Project Structure

```
invoice-management/
├── frontend/
│   ├── index.html          # Main SPA page
│   ├── login.html          # Login page
│   ├── styles/
│   │   └── main.css        # Complete design system
│   └── js/
│       ├── config.js       # API configuration
│       ├── auth.js         # Authentication manager
│       ├── api.js          # HTTP API client
│       ├── ui.js           # UI rendering engine
│       └── main.js         # App initialization
├── backend/
│   ├── config.php          # Database configuration
│   ├── api/
│   │   ├── index.php       # API entry point
│   │   ├── router.php      # Route definitions
│   │   ├── auth.php        # Auth middleware
│   │   └── response.php    # Response helpers
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── ClientController.php
│   │   ├── InvoiceController.php
│   │   ├── InvoiceUpdateController.php
│   │   ├── PaymentController.php
│   │   ├── DashboardController.php
│   │   ├── SettingsController.php
│   │   └── PdfController.php
│   ├── services/
│   │   ├── InvoiceService.php
│   │   ├── PaymentService.php
│   │   ├── PDFService.php
│   │   └── Logger.php
│   └── helpers/
│       └── Validator.php
├── schema.sql              # Database schema
└── README.md               # This file
```

---

## 🔌 API Endpoints

| Route | Method | Description |
|-------|--------|-------------|
| `auth.login` | POST | User login |
| `client.list` | GET | List all clients |
| `client.create` | POST | Create a client |
| `client.update` | PUT | Update a client |
| `client.delete` | DELETE | Archive a client |
| `invoice.list` | GET | List all invoices |
| `invoice.create` | POST | Create an invoice |
| `invoice.get` | GET | Get invoice details |
| `invoice.update` | PUT | Update an invoice |
| `invoice.delete` | DELETE | Soft-delete invoice |
| `payment.list` | GET | List all payments |
| `payment.create` | POST | Record a payment |
| `dashboard.stats` | GET | Dashboard statistics |
| `settings.get` | GET | Get user settings |
| `settings.update` | POST | Update settings |

---

## 🔐 Security Features

- Bearer token authentication
- CSRF protection
- SQL injection prevention (prepared statements)
- XSS protection (HTML escaping)
- Rate limiting
- Soft deletes (data preservation)
- Input validation on all endpoints

---

## 🎯 Customization

### Change Currency
Edit the currency symbol in `frontend/js/ui.js`:
```javascript
formatCurrency(amount, currency = 'INR') {
    const symbols = { INR: '₹', USD: '$', EUR: '€', GBP: '£' };
    // Add more currencies as needed
}
```

### Change Branding
- **App Name:** Search for "InvoicePro" in `login.html` and `index.html`
- **Colors:** Edit CSS custom properties in `styles/main.css` (`:root` section)
- **Logo:** Upload via Settings → Business Info

---

## 📞 Support

For installation help, customization, or feature requests, please contact the developer.

---

**Built with ❤️ by a professional developer**
