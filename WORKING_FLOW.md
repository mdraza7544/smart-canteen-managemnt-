# Smart College Canteen — Working Flow

## Roles

- **Student:** register, sign in, manage a personal wallet, order food, track orders and reserve tables.
- **Admin:** manage menu, users, inventory, order progress, reservations, offers and reports.

## Student order flow

1. Student registers with name, college email, unique student ID, phone, department and password.
2. The registration transaction creates an independent wallet with a ₹0 balance.
3. The student can use the menu or the rule-based recommendations for affordable items and meal combinations.
4. The cart keeps item quantities. Checkout rechecks prices, availability, stock and wallet balance on the server.
5. One database transaction creates the order, snapshots item prices, deducts wallet funds and inventory, records both ledgers, clears the cart and sends notifications.
6. The student views an order number, pickup token and progress through pending, accepted, preparing, ready for pickup and completed.
7. A cancellation before preparation returns the wallet amount and restores stock with movement history.

## Simulated wallet flow

1. Student chooses an amount and simulated UPI, card or net banking method.
2. The server stores a short-lived confirmation draft and unique demo reference in the session.
3. Student selects an approved or declined simulation result.
4. The server rechecks the session draft and writes the payment result. On approval it locks and credits the wallet and writes a successful transaction. On decline it writes a failed payment and failed wallet ledger entry without changing the wallet.
5. Both results provide a receipt and transaction history. The workflow is a demo and never charges or transfers real money.

## Recommendation flow

The server reads the student's current wallet balance and currently available menu stock. It recommends affordable single items and unique two or three item combinations. Combinations are ranked using popularity and how well they use the available budget. Inventory and balance are checked again when placing the order.

## Reservation flow

1. Student chooses a valid date, active time slot and party size.
2. The browser calls `public/api/availability.php` to fetch current table capacity and booking status.
3. A green table can be selected. Booked tables are shown red; disabled or undersized tables are grey.
4. The server checks the date, slot start time, capacity and overlapping bookings again. It locks the table row before inserting and relies on a unique active booking key to guard concurrent requests.
5. The student can review and cancel future reservations. Admin can edit tables and slots and confirm, reject, cancel or complete a booking.

## Inventory and admin flow

Menu changes and manual stock adjustments are recorded in `stock_movements`. Checkout deducts quantities inside its wallet/order transaction. Eligible cancellation restores them. Zero stock automatically sets an item unavailable; low and out-of-stock thresholds notify administrators.

The admin dashboard summarizes students, orders, wallet sales, reservations and low stock. Reports include daily, weekly and monthly sales, successful wallet recharges, item popularity, cancellations, reservations and stock movements, with CSV downloads for sales, inventory and wallet activity.

## Relevant implementation files

- `public/index.php` — front controller
- `controllers/AuthController.php` — authentication and password recovery
- `controllers/CollegeController.php` — student/admin workflows
- `config/functions.php` — session, CSRF, output escaping, role guards and rendering
- `database.sql` — current schema and sample data
- `migrations/001_college_migration.php` — optional legacy data importer
- `public/api/availability.php` — live reservation availability endpoint
- `views/college/page.php` — student and admin pages
