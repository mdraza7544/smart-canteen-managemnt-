# Smart College Canteen Management System

SMEIC is a PHP and MySQL canteen app for college students and canteen administrators. It keeps the existing MVC-lite structure: `public/index.php` routes requests to PHP controllers, PDO handles persistence, and PHP templates render the responsive interface.

## Features

- Student sign-up with college email, student ID, phone and department. Each new account gets its own wallet at ₹0.
- Server-validated simulated wallet payments with success and decline flows, unique receipts and a wallet ledger. No real payment is taken.
- Food categories, menu search and filters, vegetarian labels, images, preparation time and live inventory.
- Rule-based budget picks and unique two or three item combinations, ranked using popularity and value while respecting wallet balance and stock.
- Cart, wallet checkout, pickup token, order progress timeline, cancellation refund and stock restoration.
- Live table availability for a chosen date, time slot and group size. Table row locking and a database uniqueness constraint prevent double bookings, including overlapping time slots.
- Admin tools for students, categories, menu items, inventory and stock history, orders, tables, time slots, reservations, wallet and demo payment records, offers, notifications, settings and reports.
- Sales, wallet and inventory CSV exports; daily, weekly and monthly sales summaries.
- In-app notifications, CSRF tokens, prepared SQL statements, role checks, password hashing and validated image uploads.

Only two roles are active: `student` and `admin`. The running college database has no parent accounts, parent transfers, child links, or canteen-staff accounts.

## Requirements

- PHP 7.4+ with PDO MySQL, Fileinfo and standard session support (XAMPP includes these)
- MySQL 5.7+ or MariaDB with InnoDB and generated columns
- Apache or another PHP web server
- Browser access to the Tailwind CDN for styles; menu seed images use HTTPS image URLs

## Install with XAMPP

1. Start Apache and MySQL in the XAMPP control panel.
2. Import `database.sql` in phpMyAdmin, or from Command Prompt run:

   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root < database.sql
   ```

   The script creates `smeic_college_canteen` and its sample menu, stock, tables and time slots. It does not drop another database.

3. Open `config/database.php`. The default connection uses host `127.0.0.1`, database `smeic_college_canteen`, user `root` and an empty password. Change these values or configure `SMEIC_DB_HOST`, `SMEIC_DB_NAME`, `SMEIC_DB_USER` and `SMEIC_DB_PASS` for your web server.
4. Visit [http://localhost/SMEIC/public/](http://localhost/SMEIC/public/).

Composer packages are not required for the application flows. The project keeps Composer metadata for optional integrations.

## Demo accounts

| Role | Login | Password |
|---|---|---|
| Admin | `admin@canteen.com` | `Admin@123` |
| Student | `aarav@campus.edu` or `S10001` | `Student@123` |

The demo student wallet starts at ₹0. Use **My wallet → Add money** to demonstrate the payment confirmation, simulated approval and decline, server-side wallet credit, receipt and history.

## Migrate an existing SMEIC database

The migration leaves the original `smeic_canteen` database unchanged and imports compatible student accounts, wallet balances, menu items, orders, student ledger entries and notifications into the new college database. Parent and canteen accounts are intentionally not imported. Imported students sign in with their old student ID and password and must change the password on first sign-in. Imported students without an email in the old schema receive a generated `@legacy.college` email; they can replace it from their profile.

1. Back up the old database.
2. Import the new `database.sql` first.
3. From PowerShell, run the importer:

   ```powershell
   $env:SMEIC_DB_NAME = 'smeic_college_canteen'
   $env:SMEIC_LEGACY_DB_NAME = 'smeic_canteen'
   & 'C:\xampp\php\php.exe' .\migrations\001_college_migration.php
   ```

   The importer is repeatable and records imported rows. It needs database read access to the legacy database and write access to the new one. The application already defaults to the new database after import.

## Main routes

- `index.php?action=register` — student registration
- `index.php?action=login` — student or admin sign-in
- Student pages: dashboard, menu, recommendations, cart, checkout, orders, wallet, reservations, notifications and profile under `student/*`
- Admin pages: dashboard, users, categories, menu, inventory, orders, tables, reservations, transactions, payments, offers, notifications, reports and settings under `admin/*`
- `public/api/availability.php` — signed-in student table availability lookup
- `public/api/update-cart.php` — CSRF-protected cart update API

## Manual verification

1. Register a new student. Confirm their wallet shows ₹0, then sign out and sign in with their email or student ID.
2. In the wallet, try a recharge below ₹10 and above ₹10,000 (both should be rejected). Complete one approved demo recharge and one declined demo recharge. Confirm only the approved attempt changes the balance and that both attempts have unique references and receipts.
3. Filter the menu by category, food type, search term and maximum price. Add an item and a recommended combination. Confirm unavailable and zero-stock foods do not appear.
4. Place an order with enough wallet funds. Confirm the order number and pickup token, wallet debit, stock deduction, student/admin notifications and status timeline. Try a second order with insufficient funds. Cancel a pending order and check the refund and stock movement.
5. Select a date, time and group size on the reservation page. Confirm green tables can be reserved, booked tables turn red on refresh, too-small and disabled tables cannot be selected, and reservations in the past are rejected. Try reserving the same table and slot in two browser sessions; only one should succeed.
6. Sign in as admin. Update an order through accepted, preparing, ready and completed; change a table/slot; add stock; and inspect low-stock alerts and movement history.
7. Create an active offer and confirm its discount is shown at checkout. Review daily, weekly and monthly sales reports and download each available CSV export.

## Demo boundaries

The recharge gateway is a local academic simulation. Its approve/decline choice is handled by PHP and database transactions, but it is not a payment provider and does not move real money. Password reset links are logged to `logs/mail.log` in local demo mode; connect SMTP before using email delivery in a deployed environment.
