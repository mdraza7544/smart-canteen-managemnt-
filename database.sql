-- ============================================================================
-- Smart College Canteen Management System — fresh install schema and demo data
-- Optional legacy data import: run migrations/001_college_migration.php after this schema.
--   mysql -u root < database.sql
-- Demo: admin@canteen.com / Admin@123; aarav@campus.edu / Student@123
-- ============================================================================

CREATE DATABASE IF NOT EXISTS smeic_college_canteen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smeic_college_canteen;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','student') NOT NULL DEFAULT 'student',
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    student_id VARCHAR(30) DEFAULT NULL UNIQUE,
    department VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    force_password_change TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_users_role (role),
    KEY idx_users_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    purpose ENUM('recharge','order_payment','refund','admin_adjustment') NOT NULL,
    reference VARCHAR(255) DEFAULT NULL,
    txn_ref VARCHAR(40) NOT NULL UNIQUE,
    balance_after DECIMAL(10,2) NOT NULL,
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wt_user_created (user_id, created_at),
    CONSTRAINT fk_wt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('upi','card','netbanking') NOT NULL,
    gateway_txn_id VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    failure_reason VARCHAR(255) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pt_user_created (user_id, created_at),
    KEY idx_pt_status (status),
    CONSTRAINT fk_pt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS food_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    food_type ENUM('vegetarian','non_vegetarian') NOT NULL DEFAULT 'vegetarian',
    image VARCHAR(500) DEFAULT NULL,
    preparation_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    popularity INT UNSIGNED NOT NULL DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_menu_category (category_id),
    KEY idx_menu_available (is_available),
    KEY idx_menu_price (price),
    CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES food_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL UNIQUE,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    minimum_quantity INT UNSIGNED NOT NULL DEFAULT 5,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    previous_quantity INT UNSIGNED NOT NULL,
    added_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    deducted_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_quantity INT UNSIGNED NOT NULL,
    reason VARCHAR(180) NOT NULL,
    admin_id INT DEFAULT NULL,
    order_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stock_item_created (menu_item_id, created_at),
    CONSTRAINT fk_stock_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cart_user_item (user_id, menu_item_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(32) NOT NULL UNIQUE,
    pickup_token CHAR(6) NOT NULL UNIQUE,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','accepted','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_status_created (status, created_at),
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_item_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS canteen_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(30) NOT NULL UNIQUE,
    capacity SMALLINT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(80) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_slot_time (start_time, end_time)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS table_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    table_id INT NOT NULL,
    slot_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    party_size SMALLINT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled','rejected') NOT NULL DEFAULT 'confirmed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active_booking TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE NULL END) STORED,
    UNIQUE KEY uniq_active_table_slot (table_id, reservation_date, slot_id, active_booking),
    KEY idx_reservations_date_slot (reservation_date, slot_id, status),
    KEY idx_reservations_user (user_id, reservation_date),
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_table FOREIGN KEY (table_id) REFERENCES canteen_tables(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_slot FOREIGN KEY (slot_id) REFERENCES reservation_slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('student','admin') NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notification_recipient (recipient_type, user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_offer_live (is_active, starts_at, ends_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_reset_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO users (id, role, full_name, email, password, is_active)
VALUES (1, 'admin', 'Canteen Administrator', 'admin@canteen.com', '$2y$10$M.o3XRft7zxpVs2ReXxW0.3fdgjYtBn2kTS4MOAIBKLBv8iHpjS1.', 1);
INSERT IGNORE INTO users (id, role, full_name, email, phone, student_id, department, password, is_active)
VALUES (2, 'student', 'Aarav Kumar', 'aarav@campus.edu', '9000000001', 'S10001', 'Computer Science', '$2y$10$A/dxNBLG9/QikjGKZ59ZZ.TerSSz9zusOoTk5pXjgvk9OxtfMmGAy', 1);
INSERT IGNORE INTO wallets (user_id, balance) VALUES (2, 0.00);

INSERT IGNORE INTO food_categories (id, name, sort_order) VALUES
(1,'Breakfast',1),(2,'Lunch',2),(3,'Snacks',3),(4,'Fast Food',4),(5,'Beverages',5),(6,'Desserts',6),(7,'Healthy Food',7);

INSERT IGNORE INTO menu_items (id, category_id, name, description, price, food_type, image, preparation_minutes, popularity, is_available) VALUES
(1,1,'Masala Dosa','Crisp dosa with spiced potato, chutney and sambar.',30,'vegetarian','https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=800&q=80',12,92,1),
(2,1,'Idli (2 pcs)','Soft steamed idlis served with sambar.',20,'vegetarian','https://images.unsplash.com/photo-1589301760014-d929f3979dbc?auto=format&fit=crop&w=800&q=80',8,80,1),
(3,2,'Veg Pulao','Basmati rice cooked with seasonal vegetables.',65,'vegetarian','https://images.unsplash.com/photo-1596797038530-2c107229654b?auto=format&fit=crop&w=800&q=80',15,65,1),
(4,2,'Chicken Biryani','Fragrant rice with spiced chicken and raita.',110,'non_vegetarian','https://images.unsplash.com/photo-1563379091339-03246963d96c?auto=format&fit=crop&w=800&q=80',20,88,1),
(5,3,'Samosa (2 pcs)','Golden potato samosas with mint chutney.',25,'vegetarian','https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=800&q=80',6,72,1),
(6,4,'Veg Sandwich','Toasted vegetable and cheese sandwich.',35,'vegetarian','https://images.unsplash.com/photo-1528735602780-2552fd46c7af?auto=format&fit=crop&w=800&q=80',10,74,1),
(7,5,'Masala Chai','Freshly brewed tea with ginger and spices.',10,'vegetarian','https://images.unsplash.com/photo-1571934811356-5cc061b6821f?auto=format&fit=crop&w=800&q=80',5,90,1),
(8,5,'Fresh Lime Juice','Fresh lime, chilled water and a hint of mint.',25,'vegetarian','https://images.unsplash.com/photo-1513558161293-cdaf765edfd4?auto=format&fit=crop&w=800&q=80',5,61,1),
(9,6,'Chocolate Brownie','Rich chocolate brownie baked in-house.',40,'vegetarian','https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=800&q=80',5,55,1),
(10,7,'Fruit Bowl','Seasonal fruit with a squeeze of lime.',45,'vegetarian','https://images.unsplash.com/photo-1511690743698-d9d85f2fbf38?auto=format&fit=crop&w=800&q=80',5,48,1);

INSERT IGNORE INTO inventory (menu_item_id, quantity, minimum_quantity) VALUES
(1,24,5),(2,30,6),(3,18,5),(4,12,4),(5,40,8),(6,20,5),(7,60,10),(8,22,5),(9,14,4),(10,16,5);

INSERT IGNORE INTO canteen_tables (id, table_number, capacity, is_enabled) VALUES
(1,'T-01',2,1),(2,'T-02',2,1),(3,'T-03',4,1),(4,'T-04',4,1),(5,'T-05',6,1),(6,'T-06',6,1);
INSERT IGNORE INTO reservation_slots (id,label,start_time,end_time,is_active) VALUES
(1,'Morning Break','09:00:00','10:00:00',1),(2,'Lunch 1','12:00:00','13:00:00',1),(3,'Lunch 2','13:00:00','14:00:00',1),(4,'Afternoon Break','15:00:00','16:00:00',1);
INSERT IGNORE INTO admin_settings (setting_key, setting_value) VALUES
('canteen_name','Campus Canteen'),('reservation_policy','Reservations can be cancelled any time before the reserved slot.'),('wallet_gateway','Simulated demo gateway only. No real payment is processed.');
