<<<<<<< HEAD
<div align="center">

# SMEIC

### School Canteen Management System

A full-featured, role-based web application for managing school canteen operations — from menu browsing and food ordering by students, to wallet management by parents, to order processing by canteen staff, to full administrative oversight.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![JavaScript](https://img.shields.io/badge/Vanilla_JS-ES6-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**Everything a school canteen needs, in one clean dashboard.**

[Getting Started](#getting-started) &bull; [Features](#features) &bull; [Architecture](#architecture) &bull; [Security](#security) &bull; [API Reference](#api-reference) &bull; [Contributors](#contributors)

</div>

---

## Table of Contents

- [About](#about)
- [Key Highlights](#key-highlights)
- [Getting Started](#getting-started)
- [Default Accounts](#default-accounts)
- [Features](#features)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Order Lifecycle](#order-lifecycle)
- [Security](#security)
- [API Reference](#api-reference)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Scalability](#scalability)
- [Deployment](#deployment)
- [Contributors](#contributors)

---

## About

SMEIC (School Canteen Management System) is a complete, production-ready web application built for schools to digitize and streamline their canteen operations. It supports **four distinct roles** — Admin, Student, Parent, and Canteen Staff — each with a dedicated dashboard and role-specific functionality.

The system handles the entire canteen workflow: administrators manage students and parents, students browse menus and place orders with digital wallet payments, parents top up their children's wallets and track spending, and canteen staff process orders with real-time status tracking and pickup token verification.

Built with **pure PHP** (no framework), **MySQL**, and **TailwindCSS**, SMEIC is designed to run on any LAMP/XAMPP stack — including affordable shared hosting — making it accessible for schools with limited IT infrastructure.

---

## Key Highlights

| Feature | Detail |
|---|---|
| **4 Role-Based Dashboards** | Admin, Student, Parent, Canteen Staff — each with tailored KPIs and charts |
| **Digital Wallet System** | Atomic fund transfers, top-ups, and real-time balance tracking |
| **Live Order Tracking** | Visual timeline from Pending → Accepted → Preparing → Ready → Picked Up |
| **QR Code Pickup Tokens** | 6-digit cryptographically secure tokens with offline QR generation |
| **3-Minute Pickup Reminders** | Automatic notifications when food is ready but uncollected |
| **CSV & PDF Export** | Admin reports exportable in multiple formats |
| **In-App Notifications** | Real-time notification polling with toast alerts and bell badge |
| **Atomic Financial Operations** | `SELECT ... FOR UPDATE` row locks + PDO transactions for wallet integrity |
| **Enterprise-Grade Security** | CSRF, XSS prevention, bcrypt passwords, prepared statements, security headers |
| **Zero Framework Dependencies** | Runs on any shared hosting with PHP 8.0+ and MySQL |

---

## Getting Started

### Prerequisites

- **XAMPP** (or any Apache + MySQL + PHP setup)
- **PHP 8.0** or higher
- **MySQL 5.7+** / MariaDB 10.3+
- **Composer** (optional — for PDF/QR features)

### Installation

**Step 1: Clone or copy the project**

```bash
git clone https://github.com/your-username/SMEIC.git C:\xampp\htdocs\SMEIC
```

Or place the project folder directly in `C:\xampp\htdocs\SMEIC`.

**Step 2: Start XAMPP**

Launch XAMPP Control Panel and start both **Apache** and **MySQL**.

**Step 3: Create the database**

Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin) in your browser:

1. Go to the **Import** tab
2. Click **Choose File** and select `database.sql` from the project root
3. Click **Go** to import

This creates the `smeic_canteen` database with all 19 tables and seed data.

Alternatively, use the command line:
```bash
mysql -u root < database.sql
```

**Step 4: Generate password hashes (one-time)**

Open [http://localhost/SMEIC/public/generate_hashes.php](http://localhost/SMEIC/public/generate_hashes.php) in your browser. This sets real bcrypt hashes for all default demo accounts so the login credentials work.

**Step 5: Install Composer dependencies (optional)**

```bash
cd C:\xampp\htdocs\SMEIC
C:\xampp\php\php.exe C:\xampp\composer\composer.phar install
```

This installs `dompdf` (PDF export), `phpmailer` (email), and `chillerlan/php-qrcode` (offline QR codes). The app works without them — it gracefully falls back to CSV for PDFs and an online QR API.

**Step 6: Launch the application**

Open [http://localhost/SMEIC/public/](http://localhost/SMEIC/public/) in your browser.

> **Note:** Default database credentials are XAMPP defaults — host `127.0.0.1`, user `root`, password empty. If yours differ, update `config/database.php`.

---

## Default Accounts

All accounts force a password change on first login for security.

| Role | Login ID | Password |
|---|---|---|
| **Admin** | `admin@canteen.com` | `Admin@123` |
| **Student** | `S10001` (or `S10002`) | `Student@123` |
| **Parent** | `P10001` (or `P10002`) | `Parent@123` |
| **Canteen Staff** | `canteen@canteen.com` | `Canteen@123` |

After logging in with the default password, you will be redirected to set a new password before accessing the dashboard.

---

## Features

### Admin Dashboard

- **KPI Overview** — Total students, today's orders, monthly & all-time revenue, pending order count
- **Interactive Charts** — Weekly order bar chart, monthly revenue trend line chart (Chart.js)
- **Student Management** — Full CRUD with auto-generated `S#####` IDs, random password assignment, soft delete, password reset
- **Parent Management** — Full CRUD with auto-generated `P#####` IDs, linked to children
- **Wallet Top-Up** — Credit parent or student wallets with audit trail
- **Order Management** — Filterable order list by date range, status, and student
- **Transaction Ledger** — Filterable wallet transaction history
- **Menu Viewer** — Read-only access to canteen menu
- **Reports** — Summary statistics across all metrics
- **Audit Log** — Complete trail of every admin action
- **Data Export** — CSV and PDF export for orders, transactions, and menu items

### Student Experience

- **Smart Dashboard** — Wallet balance, monthly spending, behavior score, low-balance warnings (threshold: ₹50)
- **Menu Browsing** — Category filters (Veg / Non-Veg / Snacks / Drinks / Desserts), search, daily special banner
- **AJAX Cart** — Add-to-cart with live badge updates, quantity controls, wallet balance check
- **Atomic Checkout** — Secure order placement with wallet deduction, transaction recording, and instant notifications
- **Live Order Tracking** — Visual timeline showing real-time order status
- **QR Pickup Token** — 6-digit token with QR code (offline via `chillerlan/php-qrcode` or online fallback)
- **Transaction History** — Complete wallet transaction log
- **Notifications** — Real-time in-app notifications with 10-second polling
- **Order Feedback** — 1-5 star rating with comment on completed orders
- **Profile** — View personal information

### Parent Portal

- **Dual Wallet View** — See own wallet balance alongside each child's wallet and behavior score
- **Fund Transfer** — Atomic transfer to child's wallet with double transaction record (debit + credit)
- **Menu Access** — Browse the canteen menu in read-only mode
- **Meal Preferences** — Send daily meal suggestions/notes to child
- **Child Order Tracking** — Filterable order history with full timeline and detail view
- **Notifications** — Stay updated on child's activity
- **Profile** — View personal information

### Canteen Staff Panel

- **Operations Dashboard** — Today's earnings, orders completed, pending count, monthly chart, top 5 selling items
- **Order Processing** — Accept orders (with prep time estimate), mark ready, mark picked up, cancel with auto-refund
- **Token Verification** — QR scan or manual 6-digit token entry with rate limiting (5 attempts per 15 minutes)
- **Menu Management** — Full CRUD with image upload (validated MIME type, size, and dimensions)
- **Daily Specials** — Highlight one menu item per day (auto-deactivates previous)
- **Earnings Reports** — Today, 7-day, and 30-day breakdowns
- **Staff Management** — Manager-only: create/edit staff accounts, set granular permissions (verify tokens, complete orders, view pending), toggle active status

### System Features

- **CSRF Protection** — Token-based protection on every form submission
- **XSS Prevention** — All output escaped via `htmlspecialchars()`
- **Session Security** — Regenerated on login, HttpOnly, SameSite=Lax
- **Security Headers** — X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, CSP, HSTS
- **File Upload Security** — MIME validation, size limits, dimension checks, renamed filenames
- **Audit Logging** — Every admin action tracked with timestamp and details
- **Flash Messages** — Auto-dismissing success/error notifications
- **Mobile Responsive** — Collapsible sidebar, responsive layout for all screen sizes

---

## Architecture

SMEIC follows a **Front Controller** pattern — every HTTP request enters through a single `public/index.php` file, which routes the request to the appropriate controller method.

```
HTTP Request
    │
    ▼
public/index.php  ──► Extracts "action" parameter
    │
    ├──► AuthController::login()
    ├──► AdminController::dashboard()
    ├──► StudentController::checkout()
    ├──► ParentController::transfer()
    ├──► CanteenController::acceptOrder()
    │
    ▼
Controller Method
    ├──► require_role(['student'])   ◄── Access control
    ├──► CSRF check                  ◄── Security
    ├──► Business logic (PDO queries)
    ├──► Database operations
    ├──► Notifications
    └──► View rendering (header + sidebar + content + footer)
```

### Design Principles

- **No Framework** — Pure PHP 8.0+ for maximum portability and zero learning curve
- **Convention over Configuration** — One controller per role, one view file per page
- **Security by Default** — Prepared statements, CSRF, XSS prevention, role guards on every method
- **Atomic Operations** — Database transactions with row-level locks for all financial operations
- **Graceful Degradation** — Works without Composer dependencies (falls back to online services)

---

## Project Structure

```
SMEIC/
│
├── config/                          # Application configuration (NOT web-accessible)
│   ├── constants.php                # App name, URLs, paths, thresholds, currency (₹)
│   ├── database.php                 # PDO database connection singleton
│   └── functions.php                # All global helper functions (394 lines)
│
├── controllers/                     # Business logic — one class per role
│   ├── AuthController.php           # Login, logout, password change/reset
│   ├── AdminController.php          # Dashboard, CRUD, reports, exports
│   ├── StudentController.php        # Menu, cart, checkout, orders, tracking
│   ├── ParentController.php         # Transfers, preferences, child orders
│   └── CanteenController.php        # Order processing, menu CRUD, earnings
│
├── views/                           # PHP + HTML templates (no template engine)
│   ├── layouts/                     # header.php, sidebar.php, footer.php
│   ├── auth/                        # Login, forgot-password, reset-password
│   ├── admin/                       # Admin dashboard, students, parents, etc.
│   ├── student/                     # Student dashboard, menu, cart, orders
│   ├── parent/                      # Parent dashboard, transfers, preferences
│   └── canteen/                     # Canteen dashboard, orders, menu, earnings
│
├── public/                          # Web root — only this folder is HTTP-accessible
│   ├── index.php                    # Front controller / router (348 lines)
│   ├── generate_hashes.php          # One-time password hash generator
│   ├── assets/
│   │   ├── css/style.css            # Custom CSS design system (1,276 lines)
│   │   ├── js/app.js                # AJAX helper, toasts, password toggle
│   │   ├── js/notifications.js      # 10s notification poll + pickup reminder
│   │   ├── img/                     # Favicon, placeholder images
│   │   └── uploads/menu/            # Uploaded menu item images
│   └── api/                         # JSON-only AJAX endpoints
│       ├── _bootstrap.php           # Shared API initialization
│       ├── update-cart.php          # Cart operations
│       ├── transfer-funds.php       # Parent-to-child fund transfer
│       ├── check-notifications.php  # Unread notification count
│       ├── get-order-status.php     # Live order status
│       └── ...                      # Other API endpoints
│
├── database.sql                     # Complete schema + seed data (378 lines)
├── composer.json                    # PHP dependencies
├── .htaccess                        # Root access control rules
├── backend.txt                      # Detailed backend documentation
└── README.md                        # This file
```

---

## Database Schema

The database `smeic_canteen` contains **19 tables** built on MySQL/InnoDB:

### Core Tables

| Table | Purpose |
|---|---|
| `users` | Unified authentication table for admin, parent, and canteen roles |
| `students` | Student profiles with class, roll number, behavior score, parent link |
| `parents` | Parent profiles with wallet balance |
| `canteen_staff` | Canteen staff accounts with granular permissions |
| `student_parent_links` | Many-to-many bridge for future multi-parent support |

### Financial Tables

| Table | Purpose |
|---|---|
| `wallets` | Student wallet balances (linked to students table) |
| `transactions` | Complete ledger of all wallet movements (credits and debits) |

### Order & Menu Tables

| Table | Purpose |
|---|---|
| `orders` | Food orders with status tracking and pickup tokens |
| `order_items` | Individual items within each order (snapshot at time of purchase) |
| `menu_items` | Canteen menu with name, price, category, image, availability |
| `daily_specials` | Today's highlighted menu item |
| `carts` | Active student shopping cart |

### System Tables

| Table | Purpose |
|---|---|
| `notifications` | In-app notification feed for all users |
| `feedback` | Student ratings and comments on completed orders |
| `password_resets` | Time-limited password reset tokens (1-hour expiry) |
| `audit_logs` | Complete trail of admin actions |
| `parent_preferences` | Meal suggestions from parents to children |
| `behavioral_scores` | Audit trail of behavior score changes |
| `staff_activity_log` | Canteen staff activity tracking |
| `token_verification_log` | Pickup token verification audit |

---

## Order Lifecycle

```
┌─────────┐     ┌──────────┐     ┌───────────┐     ┌─────────┐     ┌────────────┐
│ PENDING │ ──► │ ACCEPTED │ ──► │ PREPARING │ ──► │  READY  │ ──► │ PICKED_UP  │
└─────────┘     └──────────┘     └───────────┘     └─────────┘     └────────────┘
     │               │                │                 │
     └───────────────┴────────────────┴─────────────────┘
                              │
                              ▼
                     ┌────────────┐
                     │ CANCELLED  │  (Full refund to student wallet)
                     └────────────┘
```

**Flow:**
1. **Pending** — Student places the order; wallet is debited; canteen and parent are notified
2. **Accepted** — Canteen staff acknowledges the order with prep time estimate
3. **Preparing** — Food is being prepared
4. **Ready** — Food is done; student receives notification; 3-minute pickup reminder timer starts
5. **Picked Up** — Student collects food (verified via QR token or manual entry)
6. **Cancelled** — Available at any stage before pickup; full refund issued to student wallet

**3-Minute Pickup Reminder:**
When an order reaches "ready" status, a background check runs every 60 seconds. If the order remains uncollected for 3+ minutes, the student receives up to 3 pickup reminder notifications.

---

## Security

SMEIC implements a comprehensive security model:

| Layer | Implementation |
|---|---|
| **SQL Injection** | All database queries use PDO prepared statements — zero string concatenation |
| **Cross-Site Scripting (XSS)** | All output escaped via `htmlspecialchars()` through the `e()` helper |
| **Cross-Site Request Forgery (CSRF)** | `bin2hex(random_bytes(32))` token per session, validated with `hash_equals()` |
| **Session Fixation** | `session_regenerate_id(true)` on every successful login |
| **Password Storage** | bcrypt hashing with `PASSWORD_BCRYPT` (cost 10) |
| **Session Cookie** | HttpOnly, SameSite=Lax, 4-hour lifetime, custom session name `SMEIC_SESSID` |
| **Security Headers** | X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, HSTS, Content-Security-Policy |
| **File Upload** | MIME validation via `mime_content_type()`, `getimagesize()` check, max 4MB, filenames renamed with `uniqid()` |
| **Wallet Integrity** | `SELECT ... FOR UPDATE` row-level locks inside PDO transactions |
| **Access Control** | `require_role()` guard on every controller method |
| **Directory Protection** | `.htaccess` blocks direct access to config/, controllers/, logs/, etc. |
| **Rate Limiting** | Token verification limited to 5 attempts per 15-minute window |
| **Password Reset** | 48-char random tokens with 1-hour expiry |
| **Force Password Change** | All new accounts require password change on first login |
| **Audit Trail** | Every admin action logged with timestamp, action, and details |

---

## API Reference

### Page Routes (`?action=...`)

All page routes go through `public/index.php?action=<route>`.

**Authentication:**
| Route | Description |
|---|---|
| `login` | Login page (GET) / Authenticate (POST) |
| `logout` | Destroy session and redirect |
| `forgot-password` | Request password reset |
| `reset-password` | Set new password via token |
| `change-password` | Change current password |

**Admin:**
| Route | Description |
|---|---|
| `admin/dashboard` | KPI dashboard with charts |
| `admin/students` | Student list with search |
| `admin/student-save` | Create or update student |
| `admin/student-delete` | Soft-delete student |
| `admin/student-reset` | Reset student password |
| `admin/parents` | Parent list with search |
| `admin/parent-save` | Create or update parent |
| `admin/topup` | Wallet top-up form |
| `admin/orders` | Order list with filters |
| `admin/transactions` | Transaction list with filters |
| `admin/reports` | Summary reports |
| `admin/audit` | Admin action audit log |
| `admin/export` | CSV/PDF data export |

**Student:**
| Route | Description |
|---|---|
| `student/dashboard` | Balance, spending, active order |
| `student/menu` | Browse menu with category filters |
| `student/cart` | View and manage cart |
| `student/checkout` | Order confirmation |
| `student/orders` | Order history |
| `student/order-tracking` | Live order status timeline |
| `student/token` | QR code / pickup token |
| `student/transactions` | Wallet transaction history |
| `student/notifications` | Notification feed |
| `student/feedback` | Rate completed orders |

**Parent:**
| Route | Description |
|---|---|
| `parent/dashboard` | Wallets, children, spending |
| `parent/transfer` | Transfer funds to child |
| `parent/menu` | Browse canteen menu |
| `parent/preferences` | Send meal suggestions |
| `parent/child-orders` | Child order history |

**Canteen:**
| Route | Description |
|---|---|
| `canteen/dashboard` | Earnings, orders, top sellers |
| `canteen/orders` | Order management |
| `canteen/accept-order` | Accept with prep time |
| `canteen/mark-ready` | Mark order as ready |
| `canteen/mark-pickedup` | Mark order as picked up |
| `canteen/cancel-order` | Cancel with auto-refund |
| `canteen/scan-pickup` | Token verification form |
| `canteen/menu` | Menu CRUD |
| `canteen/daily-special` | Set daily special |
| `canteen/earnings` | Earnings reports |

### JSON API Endpoints (`public/api/`)

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `update-cart.php` | POST | Student | Add, update, or remove cart items |
| `transfer-funds.php` | POST | Parent | Atomic fund transfer to child |
| `check-notifications.php` | GET | Any | Unread count + latest notification |
| `mark-notification-read.php` | POST | Any | Mark notification(s) as read |
| `get-order-status.php` | GET | Any | Live order status |
| `check-pickup-reminders.php` | GET | Any | Dispatch pickup reminders |
| `mark-ready-pickup-reminder.php` | GET/POST | Any | Manual pickup reminder |

---

## Configuration

### `config/constants.php`

| Constant | Default | Description |
|---|---|---|
| `APP_NAME` | `SMEIC - School Canteen` | Application name |
| `BASE_URL` | `http://localhost/SMEIC/public` | Base URL for the application |
| `CURRENCY` | `₹` | Currency symbol (Indian Rupee) |
| `SESSION_NAME` | `SMEIC_SESSID` | PHP session cookie name |
| `SESSION_LIFETIME` | `14400` (4 hours) | Session timeout in seconds |
| `TOKEN_LENGTH` | `6` | Pickup token digits |
| `TOKEN_EXPIRY_HOURS` | `24` | Token validity period |
| `TOKEN_VERIFY_MAX_ATTEMPTS` | `5` | Max token verification attempts |
| `TOKEN_VERIFY_LOCKOUT_SECONDS` | `900` | Lockout duration (15 minutes) |
| `PICKUP_REMINDER_AFTER` | `180` | Seconds before first reminder (3 min) |
| `PICKUP_REMINDER_MAX` | `3` | Maximum reminder notifications |
| `LOW_BALANCE_THRESHOLD` | `50` | Low balance warning threshold |

### `config/database.php`

```php
// Database connection (PDO singleton)
host:     127.0.0.1
database: smeic_canteen
charset:  utf8mb4
user:     root
password: (empty for XAMPP defaults)
```

---

## Troubleshooting

| Issue | Solution |
|---|---|
| "Database connection failed" | Ensure MySQL is running and `database.sql` was imported successfully |
| "Invalid login" | Run `public/generate_hashes.php` once to set real bcrypt hashes for demo accounts |
| Page keeps redirecting | User has `force_password_change=1` — visit `clear_force_change.php` or change password |
| "500 Internal Server Error" | Check `logs/php_error.log` for detailed error messages |
| QR code shows broken image | Install Composer dependencies (`composer install`) or ensure internet access for online QR fallback |
| PDF export downloads HTML file | `dompdf` is missing — run `composer install` |
| Image upload fails | Ensure `public/assets/uploads/menu/` is writable (right-click → Properties → Security → Allow Modify) |
| Sidebar doesn't collapse on mobile | Use a modern browser; toggle button appears below 768px viewport width |
| Reset link not received | In demo mode, reset emails are written to `logs/mail.log` — open that file for the link |
| Force password change keeps redirecting | Log in with the default password once, then you'll be directed to the change-password screen |

---

## Scalability

### Current Capacity (Single-Server LAMP)

| Metric | Value |
|---|---|
| Concurrent Users | 50-150 simultaneous sessions |
| Daily Active Users | 1,000-5,000 |
| Database Size | ~50 MB for 1 year at 500 students |
| Order Throughput | ~100 orders/min sustained |

### Scaling to 5,000+ Concurrent Users

| Upgrade | Technology |
|---|---|
| Web Server | PHP-FPM + Nginx (replacing Apache mod_php) |
| Session Storage | Redis (replacing PHP file sessions) |
| Real-time Updates | WebSocket / Server-Sent Events (replacing polling) |
| Database | MySQL read replicas for reports and analytics |
| Background Jobs | Redis/RabbitMQ queue for mail and reminders |

---

## Deployment

### Quick Deployment to Shared Hosting

1. Purchase a domain and hosting plan (Hostinger, DigitalOcean, etc.)
2. Upload `public/` contents to the document root (`public_html/`)
3. Upload all other folders to a non-web-accessible location
4. Create a MySQL database and import `database.sql`
5. Update `config/database.php` with production credentials
6. Update `config/constants.php` → `BASE_URL` to your public URL
7. Run `generate_hashes.php` once, then **delete it** and `clear_force_change.php`
8. Configure HTTPS with Let's Encrypt (free via certbot)

### Production Requirements

```
PHP 8.1+ with extensions: pdo_mysql, mbstring, gd, intl, fileinfo
MySQL 8.0+ or MariaDB 10.6+
Apache with mod_rewrite OR Nginx + PHP-FPM
HTTPS enabled
```

---

## Contributors

<div align="center">

Developed with dedication and teamwork by:

| | Name | Role |
|---|---|---|
| <img src="https://img.shields.io/badge/Lead-Developer-blueviolet?style=for-the-badge" /> | **Mr. Krischal Pathak** | Frontend Developer |
| <img src="https://img.shields.io/badge/Backend-Developer-blue?style=for-the-badge" /> | **Mr. Rupesh Adhikari** | Backend Developer |
| <img src="https://img.shields.io/badge/Frontend-Developer-green?style=for-the-badge" /> | **Mr. Aryan Thapa** | Database Developer |
| <img src="https://img.shields.io/badge/Developer-coral?style=for-the-badge" /> | **Mrs. Jenisha Chaudhary** | Developer |

<br>

**SMEIC** — Built as a academic project, designed to production standards.

</div>

---

<div align="center">

If you found this project helpful, please give it a ⭐ on GitHub!

</div>
=======
# SMEIC
A full-featured, role-based web application for managing school canteen operations — from menu browsing and food ordering by students, to wallet management by parents, to order processing by canteen staff, to full administrative oversight.
>>>>>>> 577ea610182b680d8ce44434a705cada8fdb6d2c
