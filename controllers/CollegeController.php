<?php
declare(strict_types=1);

/** College canteen workflows. All writes are role checked, CSRF protected and server validated. */
class CollegeController
{
    public function handle(string $action): void
    {
        if ($action === 'register') { $this->register(); return; }
        if (strpos($action, 'student/') === 0) {
            require_role(['student']);
            if ($action === 'student/do') { $this->studentAction(); return; }
            $this->studentPage(substr($action, 8));
            return;
        }
        if ($action === 'admin' || strpos($action, 'admin/') === 0) {
            require_role(['admin']);
            if ($action === 'admin/do') { $this->adminAction(); return; }
            if ($action === 'admin/export') { $this->exportReport(); return; }
            $this->adminPage($action === 'admin' ? 'dashboard' : substr($action, 6));
            return;
        }
        http_response_code(404);
        render_with_layout('college/page', ['screen' => 'not-found', 'pageTitle' => 'Page not found']);
    }

    private function register(): void
    {
        if (!empty($_SESSION['user_id'])) redirect(url('index.php?action=student/dashboard'));
        $error = '';
        $form = ['full_name' => '', 'email' => '', 'student_id' => '', 'phone' => '', 'department' => ''];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = [
                'full_name' => trim((string)($_POST['full_name'] ?? '')),
                'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
                'student_id' => strtoupper(trim((string)($_POST['student_id'] ?? ''))),
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'department' => trim((string)($_POST['department'] ?? '')),
            ];
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (!csrf_check()) $error = 'Your session expired. Reload and try again.';
            elseif (text_len($form['full_name']) < 2 || text_len($form['full_name']) > 150) $error = 'Enter your full name.';
            elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid college email address.';
            elseif (!preg_match('/^[A-Z0-9][A-Z0-9-]{2,29}$/', $form['student_id'])) $error = 'Student ID must be 3–30 letters, numbers, or hyphens.';
            elseif ($form['phone'] !== '' && !preg_match('/^[0-9+() .-]{7,20}$/', $form['phone'])) $error = 'Enter a valid phone number.';
            elseif (text_len($form['department']) < 2 || text_len($form['department']) > 100) $error = 'Enter your department.';
            elseif (strlen($password) < 8) $error = 'Password must contain at least 8 characters.';
            elseif ($password !== $confirm) $error = 'Passwords do not match.';
            else {
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO users (role,full_name,email,phone,student_id,department,password) VALUES ('student',?,?,?,?,?,?)");
                    $stmt->execute([$form['full_name'], $form['email'], $form['phone'] ?: null, $form['student_id'], $form['department'], password_hash($password, PASSWORD_DEFAULT)]);
                    $userId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO wallets (user_id,balance) VALUES (?,0.00)')->execute([$userId]);
                    $this->notifyAdmins($pdo, 'New college member', $form['full_name'] . ' registered with student ID ' . $form['student_id'] . '.');
                    $pdo->commit();
                    flash_set('success', 'Account created. Your personal wallet starts at ₹0.00. You can now sign in.');
                    redirect(url('index.php?action=login&role=student'));
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = (string)$e->getCode() === '23000' ? 'That college email or student ID is already registered.' : 'We could not create the account. Please try again.';
                    if ((string)$e->getCode() !== '23000') app_log('Registration failed: ' . $e->getMessage());
                }
            }
        }
        view('auth/register', ['pageTitle' => 'Student registration', 'error' => $error, 'form' => $form]);
    }

    private function studentPage(string $screen): void
    {
        $pdo = db();
        $userId = (int)$_SESSION['user_id'];
        $u = $pdo->prepare('SELECT * FROM users WHERE id=? AND role=\'student\' AND is_active=1');
        $u->execute([$userId]);
        $student = $u->fetch();
        if (!$student) { $_SESSION = []; flash_set('error', 'Student account is unavailable.'); redirect(url('index.php?action=login')); }
        $bal = $pdo->prepare('SELECT balance FROM wallets WHERE user_id=?');
        $bal->execute([$userId]);
        $balance = (float)$bal->fetchColumn();
        $data = ['student' => $student, 'balance' => $balance, 'userId' => $userId];

        switch ($screen) {
            case 'dashboard':
                $st = $pdo->prepare("SELECT COUNT(*) total, SUM(status IN ('pending','accepted','preparing','ready')) pending, SUM(status='completed') completed FROM orders WHERE user_id=?");
                $st->execute([$userId]); $data['orderStats'] = $st->fetch();
                $st = $pdo->prepare("SELECT r.*, t.table_number, s.label AS slot_label FROM table_reservations r JOIN canteen_tables t ON t.id=r.table_id JOIN reservation_slots s ON s.id=r.slot_id WHERE r.user_id=? AND (r.reservation_date>CURDATE() OR (r.reservation_date=CURDATE() AND s.start_time>CURTIME())) AND r.status IN ('pending','confirmed') ORDER BY r.reservation_date,s.start_time LIMIT 3");
                $st->execute([$userId]); $data['upcoming'] = $st->fetchAll();
                $data['popularItems'] = $pdo->query("SELECT m.*, c.name category_name, i.quantity stock FROM menu_items m JOIN food_categories c ON c.id=m.category_id JOIN inventory i ON i.menu_item_id=m.id WHERE m.is_available=1 AND i.quantity>0 ORDER BY m.popularity DESC,m.name LIMIT 4")->fetchAll();
                $data['offers'] = $pdo->query("SELECT * FROM offers WHERE is_active=1 AND starts_at<=NOW() AND ends_at>=NOW() ORDER BY ends_at LIMIT 3")->fetchAll();
                $data['recommendations'] = $this->buildRecommendations($pdo, $balance, 4);
                break;
            case 'menu':
                $data['categories'] = $pdo->query('SELECT * FROM food_categories WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
                $data['filters'] = ['q' => trim((string)($_GET['q'] ?? '')), 'category' => (int)($_GET['category'] ?? 0), 'food' => (string)($_GET['food'] ?? ''), 'max' => (string)($_GET['max'] ?? ''), 'sort' => (string)($_GET['sort'] ?? 'popular')];
                $data['items'] = $this->menuItems($pdo, $data['filters']);
                break;
            case 'recommendations': $data['recommendations'] = $this->buildRecommendations($pdo, $balance, 12); break;
            case 'wallet':
            case 'transactions':
                $data['transactions'] = $this->walletTransactions($pdo, $userId);
                $data['payments'] = $this->paymentTransactions($pdo, $userId);
                break;
            case 'recharge': $data['draft'] = $_SESSION['recharge_draft'] ?? null; break;
            case 'recharge-confirm': $data['draft'] = $_SESSION['recharge_draft'] ?? null; break;
            case 'payment-receipt':
                $ref = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['ref'] ?? ''));
                $st = $pdo->prepare('SELECT * FROM payment_transactions WHERE gateway_txn_id=? AND user_id=?');
                $st->execute([$ref, $userId]); $data['payment'] = $st->fetch();
                if (!$data['payment']) { flash_set('error', 'Payment receipt was not found.'); redirect(url('index.php?action=student/wallet')); }
                break;
            case 'cart':
                $data['cartItems'] = $this->cartItems($pdo, $userId); $data['cartSubtotal']=$this->cartTotal($data['cartItems']); $data['offer']=$this->activeOffer($pdo);
                $data['discountAmount']=round($data['cartSubtotal']*(float)($data['offer']['discount_percent']??0)/100,2); $data['payable']=round($data['cartSubtotal']-$data['discountAmount'],2); break;
            case 'checkout':
                $data['cartItems'] = $this->cartItems($pdo, $userId);
                $data['cartTotal'] = $this->cartTotal($data['cartItems']);
                $data['offer'] = $this->activeOffer($pdo);
                $data['discountAmount'] = round($data['cartTotal'] * (float)($data['offer']['discount_percent'] ?? 0) / 100, 2);
                $data['payable'] = round($data['cartTotal'] - $data['discountAmount'], 2);
                break;
            case 'orders':
                $st = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 100'); $st->execute([$userId]); $data['orders'] = $st->fetchAll();
                break;
            case 'order':
                $st = $pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=?'); $st->execute([(int)($_GET['id'] ?? 0), $userId]); $data['order'] = $st->fetch();
                if (!$data['order']) { flash_set('error', 'Order not found.'); redirect(url('index.php?action=student/orders')); }
                $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $st->execute([$data['order']['id']]); $data['orderItems'] = $st->fetchAll();
                break;
            case 'reservations':
                $data['slots'] = $pdo->query('SELECT * FROM reservation_slots WHERE is_active=1 ORDER BY start_time')->fetchAll();
                $requestedDate = (string)($_GET['date'] ?? date('Y-m-d'));
                $parsedDate = DateTime::createFromFormat('Y-m-d', $requestedDate);
                $data['reservationDate'] = $parsedDate && $parsedDate->format('Y-m-d') === $requestedDate ? $requestedDate : date('Y-m-d');
                $data['partySize'] = max(1, min(20, (int)($_GET['people'] ?? 2)));
                $data['activeSlot'] = (int)($_GET['slot'] ?? 0);
                $data['availableTables'] = [];
                $data['availabilityError'] = '';
                if ($data['activeSlot'] > 0) {
                    if ($data['reservationDate'] < date('Y-m-d')) {
                        $data['availabilityError'] = 'Choose today or a future date.';
                    } else {
                        $slotQuery = $pdo->prepare('SELECT start_time,end_time FROM reservation_slots WHERE id=? AND is_active=1');
                        $slotQuery->execute([$data['activeSlot']]);
                        $selectedSlot = $slotQuery->fetch();
                        if (!$selectedSlot) {
                            $data['availabilityError'] = 'That time slot is no longer active.';
                        } elseif ($data['reservationDate'] === date('Y-m-d') && $selectedSlot['start_time'] <= date('H:i:s')) {
                            $data['availabilityError'] = 'That time slot has already started. Choose a later slot or date.';
                        } else {
                            $availability = $pdo->prepare("SELECT t.id,t.table_number,t.capacity,CASE WHEN t.is_enabled=0 THEN 'disabled' WHEN t.capacity<? THEN 'too_small' WHEN EXISTS (SELECT 1 FROM table_reservations r JOIN reservation_slots booked ON booked.id=r.slot_id WHERE r.table_id=t.id AND r.reservation_date=? AND r.status IN ('pending','confirmed') AND booked.start_time<? AND booked.end_time>?) THEN 'booked' ELSE 'available' END availability FROM canteen_tables t ORDER BY t.table_number");
                            $availability->execute([$data['partySize'],$data['reservationDate'],$selectedSlot['end_time'],$selectedSlot['start_time']]);
                            $data['availableTables'] = $availability->fetchAll();
                        }
                    }
                }
                break;
            case 'reservation-history':
                $st = $pdo->prepare('SELECT r.*, t.table_number, t.capacity, s.label slot_label, s.start_time, s.end_time FROM table_reservations r JOIN canteen_tables t ON t.id=r.table_id JOIN reservation_slots s ON s.id=r.slot_id WHERE r.user_id=? ORDER BY r.reservation_date DESC,r.id DESC');
                $st->execute([$userId]); $data['reservations'] = $st->fetchAll();
                break;
            case 'notifications':
                $st = $pdo->prepare("SELECT * FROM notifications WHERE recipient_type='student' AND user_id=? ORDER BY id DESC LIMIT 100"); $st->execute([$userId]); $data['notifications'] = $st->fetchAll();
                break;
            case 'profile': break;
            default: redirect(url('index.php?action=student/dashboard'));
        }
        $titles = ['dashboard'=>'Student dashboard','menu'=>'Food menu','recommendations'=>'Budget recommendations','wallet'=>'My wallet','transactions'=>'Wallet transactions','recharge'=>'Add money','recharge-confirm'=>'Confirm demo payment','payment-receipt'=>'Demo payment receipt','cart'=>'Your cart','checkout'=>'Checkout','orders'=>'Order history','order'=>'Order tracking','reservations'=>'Table availability','reservation-history'=>'Reservation history','notifications'=>'Notifications','profile'=>'My profile'];
        $data['screen'] = $screen; $data['pageTitle'] = $titles[$screen] ?? 'College canteen';
        render_with_layout('college/page', $data);
    }

    private function adminPage(string $screen): void
    {
        $pdo = db(); $data = [];
        switch ($screen) {
            case 'dashboard':
                $data['analytics'] = [
                    'users' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn(),
                    'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
                    'today_orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()')->fetchColumn(),
                    'today_revenue' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status<>'cancelled'")->fetchColumn(),
                    'month_revenue' => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND status<>'cancelled'")->fetchColumn(),
                    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','accepted','preparing','ready')")->fetchColumn(),
                    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn(),
                    'cancelled' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetchColumn(),
                    'reservations' => (int)$pdo->query("SELECT COUNT(*) FROM table_reservations WHERE reservation_date=CURDATE() AND status IN ('pending','confirmed')")->fetchColumn(),
                    'total_reservations' => (int)$pdo->query('SELECT COUNT(*) FROM table_reservations')->fetchColumn(),
                    'available_tables' => (int)$pdo->query('SELECT COUNT(*) FROM canteen_tables WHERE is_enabled=1')->fetchColumn(),
                    'low_stock' => (int)$pdo->query('SELECT COUNT(*) FROM inventory WHERE quantity<=minimum_quantity')->fetchColumn(),
                ];
                $data['lowStockItems'] = $this->adminMenuQuery($pdo, 'stock');
                $data['recentOrders'] = $this->adminOrdersQuery($pdo, 8);
                $chart=$pdo->query("SELECT DATE(created_at) day,SUM(total_amount) revenue FROM orders WHERE status<>'cancelled' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
                $data['salesChart']=['labels'=>array_map(function($row){return date('D',strtotime($row['day']));},$chart),'values'=>array_map(function($row){return (float)$row['revenue'];},$chart)];
                break;
            case 'users': $data['users'] = $pdo->query("SELECT * FROM users WHERE role='student' ORDER BY created_at DESC")->fetchAll(); break;
            case 'categories': $data['categories'] = $pdo->query('SELECT * FROM food_categories ORDER BY sort_order,name')->fetchAll(); break;
            case 'menu': $data['categories'] = $pdo->query('SELECT * FROM food_categories ORDER BY sort_order,name')->fetchAll(); $data['items'] = $this->adminMenuQuery($pdo); break;
            case 'inventory': $data['items'] = $this->adminMenuQuery($pdo); $st = $pdo->query('SELECT sm.*,m.name item_name,u.full_name admin_name FROM stock_movements sm JOIN menu_items m ON m.id=sm.menu_item_id LEFT JOIN users u ON u.id=sm.admin_id ORDER BY sm.id DESC LIMIT 100'); $data['movements'] = $st->fetchAll(); break;
            case 'orders': $data['orders'] = $this->adminOrdersQuery($pdo); break;
            case 'tables': $data['tables'] = $pdo->query('SELECT * FROM canteen_tables ORDER BY table_number')->fetchAll(); $data['slots'] = $pdo->query('SELECT * FROM reservation_slots ORDER BY start_time')->fetchAll(); break;
            case 'reservations': $data['reservations'] = $pdo->query('SELECT r.*,u.full_name,u.student_id,t.table_number,t.capacity,s.label slot_label,s.start_time,s.end_time FROM table_reservations r JOIN users u ON u.id=r.user_id JOIN canteen_tables t ON t.id=r.table_id JOIN reservation_slots s ON s.id=r.slot_id ORDER BY r.reservation_date DESC,s.start_time DESC LIMIT 250')->fetchAll(); break;
            case 'transactions': $data['transactions'] = $pdo->query('SELECT wt.*,u.full_name,u.email,u.student_id FROM wallet_transactions wt JOIN users u ON u.id=wt.user_id ORDER BY wt.id DESC LIMIT 250')->fetchAll(); break;
            case 'payments': $data['payments'] = $pdo->query('SELECT p.*,u.full_name,u.email FROM payment_transactions p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 250')->fetchAll(); break;
            case 'offers': $data['offers'] = $pdo->query('SELECT * FROM offers ORDER BY starts_at DESC')->fetchAll(); break;
            case 'notifications': $data['notifications'] = $pdo->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 250')->fetchAll(); break;
            case 'reports':
                $data['sales'] = $pdo->query("SELECT DATE(created_at) report_date,COUNT(*) order_count,SUM(total_amount) revenue FROM orders WHERE status<>'cancelled' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY report_date DESC")->fetchAll();
                $data['weeklySales'] = $pdo->query("SELECT YEARWEEK(created_at,1) week_id,COUNT(*) order_count,SUM(total_amount) revenue FROM orders WHERE status<>'cancelled' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 12 WEEK) GROUP BY YEARWEEK(created_at,1) ORDER BY week_id DESC")->fetchAll();
                $data['monthlySales'] = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') report_month,COUNT(*) order_count,SUM(total_amount) revenue FROM orders WHERE status<>'cancelled' AND created_at>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY report_month DESC")->fetchAll();
                $data['rechargeTotal'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment_transactions WHERE status='success'")->fetchColumn();
                $data['cancelledOrders'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='cancelled'")->fetchColumn();
                $data['popular'] = $pdo->query('SELECT item_name,SUM(quantity) quantity,SUM(quantity*unit_price) revenue FROM order_items GROUP BY menu_item_id,item_name ORDER BY quantity DESC LIMIT 10')->fetchAll();
                $data['reservationStats'] = $pdo->query("SELECT status,COUNT(*) total FROM table_reservations GROUP BY status ORDER BY total DESC")->fetchAll();
                break;
            case 'settings': $data['settings'] = $pdo->query('SELECT setting_key,setting_value FROM admin_settings ORDER BY setting_key')->fetchAll(); break;
            default: redirect(url('index.php?action=admin/dashboard'));
        }
        $titles = ['dashboard'=>'Admin dashboard','users'=>'Registered students','categories'=>'Food categories','menu'=>'Food menu management','inventory'=>'Inventory management','orders'=>'Customer orders','tables'=>'Tables and time slots','reservations'=>'Table reservations','transactions'=>'Wallet transactions','payments'=>'Simulated payments','offers'=>'Offers and discounts','notifications'=>'Notifications','reports'=>'Sales and usage reports','settings'=>'Canteen settings'];
        $data['screen'] = 'admin-' . $screen; $data['pageTitle'] = $titles[$screen] ?? 'Canteen administration';
        render_with_layout('college/page', $data);
    }

    private function studentAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) { flash_set('error', 'Invalid request. Reload the page and try again.'); redirect(url('index.php?action=student/dashboard')); }
        $pdo = db(); $uid = (int)$_SESSION['user_id']; $task = (string)($_POST['task'] ?? '');
        try {
            switch ($task) {
                case 'cart-add': $this->cartAdd($pdo, $uid, (int)($_POST['item_id'] ?? 0), (int)($_POST['quantity'] ?? 1)); flash_set('success', 'Item added to your cart.'); $to='student/menu'; break;
                case 'cart-set': $this->cartSet($pdo, $uid, (int)($_POST['item_id'] ?? 0), (int)($_POST['quantity'] ?? 0)); $to='student/cart'; break;
                case 'bundle-add': $ids = array_filter(array_map('intval', explode(',', (string)($_POST['item_ids'] ?? '')))); $this->bundleAdd($pdo,$uid,$ids); flash_set('success','Meal combination added to your cart.'); $to='student/cart'; break;
                case 'checkout': $orderId=$this->checkoutOrder($pdo,$uid); flash_set('success','Order placed. Wallet and stock were updated.'); $to='student/order&id='.$orderId; break;
                case 'cancel-order': $this->cancelStudentOrder($pdo,$uid,(int)($_POST['order_id']??0)); flash_set('success','Order cancelled. Your wallet was refunded and stock restored.'); $to='student/orders'; break;
                case 'recharge-start': $this->startRecharge(); $to='student/recharge-confirm'; break;
                case 'recharge-pay': $this->processRecharge($pdo,$uid); $to='student/payment-receipt&ref='.rawurlencode((string)($_SESSION['last_payment_ref']??'')); break;
                case 'reserve': $id=$this->createReservation($pdo,$uid); flash_set('success','Your table reservation is confirmed.'); $to='student/reservation-history'; break;
                case 'cancel-reservation': $this->cancelReservation($pdo,$uid,(int)($_POST['reservation_id']??0)); flash_set('success','Reservation cancelled.'); $to='student/reservation-history'; break;
                case 'notification-read': $pdo->prepare("UPDATE notifications SET is_read=1 WHERE recipient_type='student' AND user_id=?")->execute([$uid]); flash_set('success','Notifications marked as read.'); $to='student/notifications'; break;
                case 'profile': $this->saveProfile($pdo,$uid); flash_set('success','Profile updated.'); $to='student/profile'; break;
                default: throw new RuntimeException('Choose a valid action.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log('Student action ' . $task . ' failed: ' . $e->getMessage());
            flash_set('error', $e instanceof RuntimeException ? $e->getMessage() : 'We could not complete that action. Please try again.');
            $to = in_array($task,['checkout','cart-set'],true)?'student/cart':($task==='recharge-start'?'student/wallet':($task==='reserve'?'student/reservations':'student/dashboard'));
        }
        redirect(url('index.php?action=' . $to));
    }

    private function adminAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) { flash_set('error', 'Invalid request. Reload the page and try again.'); redirect(url('index.php?action=admin/dashboard')); }
        $pdo=db(); $task=(string)($_POST['task']??''); $adminId=(int)$_SESSION['user_id'];
        try {
            switch ($task) {
                case 'user-toggle': $id=(int)($_POST['id']??0); $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND role='student'")->execute([$id]); $to='admin/users'; break;
                case 'category-save': $this->saveCategory($pdo); $to='admin/categories'; break;
                case 'item-save': $this->saveItem($pdo); $to='admin/menu'; break;
                case 'item-toggle': $id=(int)($_POST['id']??0); $pdo->prepare('UPDATE menu_items SET is_available=1-is_available WHERE id=? AND EXISTS (SELECT 1 FROM inventory WHERE menu_item_id=? AND quantity>0)')->execute([$id,$id]); $to='admin/menu'; break;
                case 'stock-adjust': $this->adjustStock($pdo,$adminId); $to='admin/inventory'; break;
                case 'order-status': $this->updateOrderStatus($pdo,$adminId); $to='admin/orders'; break;
                case 'table-save': $this->saveTable($pdo); $to='admin/tables'; break;
                case 'table-delete': $this->deleteTable($pdo); $to='admin/tables'; break;
                case 'slot-save': $this->saveSlot($pdo); $to='admin/tables'; break;
                case 'reservation-status': $this->updateReservationStatus($pdo); $to='admin/reservations'; break;
                case 'offer-save': $this->saveOffer($pdo); $to='admin/offers'; break;
                case 'offer-toggle': $pdo->prepare('UPDATE offers SET is_active=1-is_active WHERE id=?')->execute([(int)($_POST['id']??0)]); $to='admin/offers'; break;
                case 'notification-read': $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=?')->execute([(int)($_POST['id']??0)]); $to='admin/notifications'; break;
                case 'settings-save': $this->saveSettings($pdo); $to='admin/settings'; break;
                default: throw new RuntimeException('Choose a valid management action.');
            }
            if ($task !== 'user-toggle' && $task !== 'item-toggle' && $task !== 'offer-toggle' && $task !== 'notification-read') flash_set('success','Changes saved successfully.');
            audit($adminId, $task, 'Admin page action');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log('Admin action ' . $task . ' failed: ' . $e->getMessage());
            flash_set('error', $e instanceof RuntimeException ? $e->getMessage() : 'The change could not be saved. Please check the values and try again.');
            $to = 'admin/dashboard';
        }
        redirect(url('index.php?action=' . $to));
    }

    private function cartAdd(PDO $pdo,int $uid,int $itemId,int $qty): void
    {
        if ($itemId<1 || $qty<1 || $qty>20) throw new RuntimeException('Choose a quantity between 1 and 20.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT m.is_available,i.quantity FROM menu_items m JOIN inventory i ON i.menu_item_id=m.id WHERE m.id=? FOR UPDATE'); $s->execute([$itemId]); $item=$s->fetch();
        if (!$item || !(int)$item['is_available'] || (int)$item['quantity']<$qty) throw new RuntimeException('That item is unavailable or has insufficient stock.');
        $s=$pdo->prepare('SELECT quantity FROM cart_items WHERE user_id=? AND menu_item_id=? FOR UPDATE'); $s->execute([$uid,$itemId]); $existing=(int)($s->fetchColumn()?:0);
        if ($existing+$qty>(int)$item['quantity']) throw new RuntimeException('Your cart quantity is greater than the available stock.');
        $pdo->prepare('INSERT INTO cart_items (user_id,menu_item_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),added_at=NOW()')->execute([$uid,$itemId,$existing+$qty]);
        $pdo->commit();
    }

    private function cartSet(PDO $pdo,int $uid,int $itemId,int $qty): void
    {
        if ($itemId<1 || $qty<0 || $qty>20) throw new RuntimeException('Choose a quantity between 0 and 20.');
        if ($qty===0) { $pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND menu_item_id=?')->execute([$uid,$itemId]); return; }
        $s=$pdo->prepare('SELECT i.quantity,m.is_available FROM inventory i JOIN menu_items m ON m.id=i.menu_item_id WHERE i.menu_item_id=?'); $s->execute([$itemId]); $x=$s->fetch();
        if (!$x || !(int)$x['is_available'] || $qty>(int)$x['quantity']) throw new RuntimeException('The requested quantity is not currently available.');
        $pdo->prepare('UPDATE cart_items SET quantity=? WHERE user_id=? AND menu_item_id=?')->execute([$qty,$uid,$itemId]);
        flash_set('success','Cart quantity updated.');
    }

    private function bundleAdd(PDO $pdo,int $uid,array $ids): void
    {
        $ids=array_values(array_unique(array_filter($ids,function($id){return $id>0;})));
        if (!$ids || count($ids)>3) throw new RuntimeException('That recommendation is no longer available.');
        foreach($ids as $id) $this->cartAdd($pdo,$uid,$id,1);
    }

    private function checkoutOrder(PDO $pdo,int $uid): int
    {
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT c.menu_item_id,c.quantity,m.name,m.price,m.is_available,i.quantity stock FROM cart_items c JOIN menu_items m ON m.id=c.menu_item_id JOIN inventory i ON i.menu_item_id=m.id WHERE c.user_id=? ORDER BY c.menu_item_id FOR UPDATE');
        $s->execute([$uid]); $items=$s->fetchAll();
        if (!$items) throw new RuntimeException('Your cart is empty.');
        $subtotal=0.0;
        foreach($items as $it) { if (!(int)$it['is_available'] || (int)$it['stock']<(int)$it['quantity']) throw new RuntimeException($it['name'].' no longer has enough stock.'); $subtotal+=(float)$it['price']*(int)$it['quantity']; }
        $subtotal=round($subtotal,2); $offer=$this->activeOffer($pdo); $discountPercent=(float)($offer['discount_percent']??0); $discount=round($subtotal*$discountPercent/100,2); $total=round($subtotal-$discount,2);
        $w=$pdo->prepare('SELECT balance FROM wallets WHERE user_id=? FOR UPDATE'); $w->execute([$uid]); $balance=(float)$w->fetchColumn();
        if ($balance+0.00001<$total) throw new RuntimeException('Insufficient wallet balance. Add money to your demo wallet before checkout.');
        $orderNo='CC'.date('ymd').strtoupper(bin2hex(random_bytes(4))); $token=(string)random_int(100000,999999);
        $pdo->prepare("INSERT INTO orders (user_id,order_number,pickup_token,subtotal,discount_percent,discount_amount,total_amount,status) VALUES (?,?,?,?,?,?,?,'pending')")->execute([$uid,$orderNo,$token,$subtotal,$discountPercent,$discount,$total]);
        $orderId=(int)$pdo->lastInsertId(); $newBalance=round($balance-$total,2);
        $pdo->prepare('UPDATE wallets SET balance=? WHERE user_id=?')->execute([number_format($newBalance,2,'.',''),$uid]);
        $line=$pdo->prepare('INSERT INTO order_items (order_id,menu_item_id,item_name,quantity,unit_price) VALUES (?,?,?,?,?)');
        $move=$pdo->prepare("INSERT INTO stock_movements (menu_item_id,previous_quantity,added_quantity,deducted_quantity,updated_quantity,reason,order_id) VALUES (?,?,0,?,?,?,?)");
        foreach($items as $it) {
            $qty=(int)$it['quantity']; $old=(int)$it['stock']; $new=$old-$qty;
            $line->execute([$orderId,$it['menu_item_id'],$it['name'],$qty,$it['price']]);
            $changed=$pdo->prepare('UPDATE inventory SET quantity=? WHERE menu_item_id=? AND quantity>=?'); $changed->execute([$new,$it['menu_item_id'],$qty]);
            if ($changed->rowCount()!==1) throw new RuntimeException($it['name'].' stock changed. Review the cart and try again.');
            $pdo->prepare('UPDATE menu_items SET is_available=? ,popularity=popularity+? WHERE id=?')->execute([$new>0?1:0,$qty,$it['menu_item_id']]);
            $move->execute([$it['menu_item_id'],$old,$qty,$new,'Order '.$orderNo,$orderId]);
            if ($new<=(int)$pdo->query('SELECT minimum_quantity FROM inventory WHERE menu_item_id='.(int)$it['menu_item_id'])->fetchColumn()) $this->notifyAdmins($pdo,$new===0?'Food out of stock':'Low food stock',$it['name'].' has '.$new.' portions remaining.');
        }
        $ref='ORD-'.$orderNo;
        $pdo->prepare("INSERT INTO wallet_transactions (user_id,amount,type,purpose,reference,txn_ref,balance_after,status) VALUES (?,?,'debit','order_payment',?,?,?,'success')")
            ->execute([$uid,$total,'Food order '.$orderNo,$ref,$newBalance]);
        $pdo->prepare('DELETE FROM cart_items WHERE user_id=?')->execute([$uid]);
        $this->notify($pdo,'student',$uid,'Order placed','Order '.$orderNo.' is pending. Pickup token: '.$token.'.');
        $this->notifyAdmins($pdo,'New order received',$orderNo.' has been placed.');
        $pdo->commit(); return $orderId;
    }

    private function cancelStudentOrder(PDO $pdo,int $uid,int $orderId): void
    {
        $pdo->beginTransaction(); $s=$pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=? FOR UPDATE"); $s->execute([$orderId,$uid]); $order=$s->fetch();
        if (!$order || $order['status']!=='pending') throw new RuntimeException('Only a pending order can be cancelled.');
        $this->refundAndRestore($pdo,$order,'Customer cancellation');
        $pdo->commit();
    }

    private function refundAndRestore(PDO $pdo,array $order,string $reason): void
    {
        $uid=(int)$order['user_id']; $oid=(int)$order['id'];
        $w=$pdo->prepare('SELECT balance FROM wallets WHERE user_id=? FOR UPDATE'); $w->execute([$uid]); $balance=(float)$w->fetchColumn(); $new=round($balance+(float)$order['total_amount'],2);
        $pdo->prepare('UPDATE wallets SET balance=? WHERE user_id=?')->execute([number_format($new,2,'.',''),$uid]);
        $lines=$pdo->prepare('SELECT menu_item_id,quantity FROM order_items WHERE order_id=?'); $lines->execute([$oid]);
        foreach($lines->fetchAll() as $line) {
            $s=$pdo->prepare('SELECT quantity FROM inventory WHERE menu_item_id=? FOR UPDATE'); $s->execute([$line['menu_item_id']]); $old=(int)$s->fetchColumn(); $add=(int)$line['quantity']; $updated=$old+$add;
            $pdo->prepare('UPDATE inventory SET quantity=? WHERE menu_item_id=?')->execute([$updated,$line['menu_item_id']]);
            $pdo->prepare('UPDATE menu_items SET is_available=1 WHERE id=?')->execute([$line['menu_item_id']]);
            $pdo->prepare('INSERT INTO stock_movements (menu_item_id,previous_quantity,added_quantity,deducted_quantity,updated_quantity,reason,order_id) VALUES (?,?,?,0,?,?,?)')->execute([$line['menu_item_id'],$old,$add,$updated,$reason.' '.$order['order_number'],$oid]);
        }
        $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$oid]);
        $pdo->prepare("INSERT INTO wallet_transactions (user_id,amount,type,purpose,reference,txn_ref,balance_after,status) VALUES (?,?,'credit','refund',?,?,?,'success')")
            ->execute([$uid,$order['total_amount'],$reason.' '.$order['order_number'],'REF-'.bin2hex(random_bytes(10)),$new]);
        $this->notify($pdo,'student',$uid,'Order cancelled','Order '.$order['order_number'].' was cancelled and the wallet refund is complete.');
    }

    private function startRecharge(): void
    {
        $rawAmount=(string)($_POST['amount']??''); $amount=filter_var($rawAmount,FILTER_VALIDATE_FLOAT); $method=(string)($_POST['payment_method']??'');
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/',$rawAmount) || $amount===false || $amount<10 || $amount>10000) throw new RuntimeException('Recharge amount must be between ₹10 and ₹10,000, with at most two decimal places.');
        if (!in_array($method,['upi','card','netbanking'],true)) throw new RuntimeException('Choose a simulated payment method.');
        $_SESSION['recharge_draft']=['amount'=>number_format((float)$amount,2,'.',''),'method'=>$method,'ref'=>'DEMO'.strtoupper(bin2hex(random_bytes(10)))];
    }

    private function processRecharge(PDO $pdo,int $uid): void
    {
        $draft=$_SESSION['recharge_draft']??null; $result=(string)($_POST['demo_result']??'');
        if (!$draft || !in_array($result,['approve','decline'],true)) throw new RuntimeException('Start a new simulated recharge before paying.');
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id,status FROM payment_transactions WHERE gateway_txn_id=? FOR UPDATE'); $lock->execute([$draft['ref']]); $existing=$lock->fetch();
        if ($existing) { $pdo->commit(); $_SESSION['last_payment_ref']=$draft['ref']; unset($_SESSION['recharge_draft']); return; }
        $ok=$result==='approve';
        $pdo->prepare('INSERT INTO payment_transactions (user_id,amount,payment_method,gateway_txn_id,status,failure_reason,paid_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$uid,$draft['amount'],$draft['method'],$draft['ref'],$ok?'success':'failed',$ok?null:'Demo payment declined by the user.', $ok?date('Y-m-d H:i:s'):null]);
        $w=$pdo->prepare('SELECT balance FROM wallets WHERE user_id=? FOR UPDATE'); $w->execute([$uid]); $balance=(float)$w->fetchColumn();
        $new=$ok?round($balance+(float)$draft['amount'],2):$balance;
        if ($ok) $pdo->prepare('UPDATE wallets SET balance=? WHERE user_id=?')->execute([number_format($new,2,'.',''),$uid]);
        $ledgerRef='PAY-'.$draft['ref'];
        $pdo->prepare("INSERT INTO wallet_transactions (user_id,amount,type,purpose,reference,txn_ref,balance_after,status) VALUES (?,?, 'credit','recharge',?,?,?,?)")
            ->execute([$uid,$draft['amount'],'Simulated wallet recharge','WAL-'.strtoupper(bin2hex(random_bytes(8))),$new,$ok?'success':'failed']);
        $this->notify($pdo,'student',$uid,$ok?'Demo wallet recharge successful':'Demo wallet recharge failed',$ok?'₹'.number_format((float)$draft['amount'],2).' was added to your demo wallet. No real payment was processed.':'No money was added to your demo wallet. You can retry with a new demo payment.');
        $pdo->commit(); $_SESSION['last_payment_ref']=$draft['ref']; unset($_SESSION['recharge_draft']);
    }

    private function createReservation(PDO $pdo,int $uid): int
    {
        $date=(string)($_POST['reservation_date']??''); $slotId=(int)($_POST['slot_id']??0); $tableId=(int)($_POST['table_id']??0); $people=(int)($_POST['party_size']??0);
        $parsed=DateTime::createFromFormat('Y-m-d',$date); if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<date('Y-m-d')) throw new RuntimeException('Choose today or a future reservation date.');
        if ($slotId<1 || $tableId<1 || $people<1 || $people>20) throw new RuntimeException('Choose a date, time slot, table and party size.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT * FROM reservation_slots WHERE id=? AND is_active=1'); $s->execute([$slotId]); $slot=$s->fetch(); if (!$slot) throw new RuntimeException('That time slot is not available.');
        if ($date===date('Y-m-d') && $slot['start_time']<=date('H:i:s')) throw new RuntimeException('That time slot has already started.');
        $t=$pdo->prepare('SELECT * FROM canteen_tables WHERE id=? FOR UPDATE'); $t->execute([$tableId]); $table=$t->fetch();
        if (!$table || !(int)$table['is_enabled'] || (int)$table['capacity']<$people) throw new RuntimeException('The selected table cannot accommodate your party.');
        $check=$pdo->prepare("SELECT r.id FROM table_reservations r JOIN reservation_slots booked ON booked.id=r.slot_id WHERE r.table_id=? AND r.reservation_date=? AND r.status IN ('pending','confirmed') AND booked.start_time<? AND booked.end_time>? FOR UPDATE"); $check->execute([$tableId,$date,$slot['end_time'],$slot['start_time']]);
        if ($check->fetch()) throw new RuntimeException('That table was just reserved. Choose another available table.');
        $pdo->prepare("INSERT INTO table_reservations (user_id,table_id,slot_id,reservation_date,party_size,status) VALUES (?,?,?,?,?,'confirmed')")->execute([$uid,$tableId,$slotId,$date,$people]);
        $id=(int)$pdo->lastInsertId(); $pdo->prepare("INSERT INTO notifications (recipient_type,user_id,title,message) VALUES ('student',?,'Table reserved',?)")->execute([$uid,'Table '.$table['table_number'].' is reserved for '.$date.' at '.$slot['label'].'.']);
        $this->notifyAdmins($pdo,'New table reservation','Table '.$table['table_number'].' reserved for '.$date.' at '.$slot['label'].'.');
        $pdo->commit(); return $id;
    }

    private function cancelReservation(PDO $pdo,int $uid,int $id): void
    {
        $s=$pdo->prepare("UPDATE table_reservations r JOIN reservation_slots slot ON slot.id=r.slot_id SET r.status='cancelled' WHERE r.id=? AND r.user_id=? AND (r.reservation_date>CURDATE() OR (r.reservation_date=CURDATE() AND slot.start_time>CURTIME())) AND r.status IN ('pending','confirmed')"); $s->execute([$id,$uid]);
        if ($s->rowCount()!==1) throw new RuntimeException('That reservation can no longer be cancelled.');
        $this->notify($pdo,'student',$uid,'Reservation cancelled','Your table reservation has been cancelled.');
    }

    private function saveProfile(PDO $pdo,int $uid): void
    {
        $name=trim((string)($_POST['full_name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $phone=trim((string)($_POST['phone']??'')); $department=trim((string)($_POST['department']??''));
        if (text_len($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || text_len($department)<2 || ($phone!==''&&!preg_match('/^[0-9+() .-]{7,20}$/',$phone))) throw new RuntimeException('Enter a valid name, college email, department and phone number.');
        try { $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,department=? WHERE id=? AND role=\'student\'')->execute([$name,$email,$phone?:null,$department,$uid]); }
        catch(PDOException $e) { if ((string)$e->getCode()==='23000') throw new RuntimeException('That email address is already registered.'); throw $e; }
    }

    private function saveCategory(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); $name=trim((string)($_POST['name']??'')); $sort=(int)($_POST['sort_order']??0); $active=isset($_POST['is_active'])?1:0;
        if (text_len($name)<2 || text_len($name)>80) throw new RuntimeException('Category name must be 2–80 characters.');
        if ($id) $pdo->prepare('UPDATE food_categories SET name=?,sort_order=?,is_active=? WHERE id=?')->execute([$name,$sort,$active,$id]);
        else $pdo->prepare('INSERT INTO food_categories (name,sort_order,is_active) VALUES (?,?,?)')->execute([$name,$sort,$active]);
    }

    private function saveItem(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); $name=trim((string)($_POST['name']??'')); $description=trim((string)($_POST['description']??'')); $category=(int)($_POST['category_id']??0); $priceRaw=(string)($_POST['price']??''); $price=filter_var($priceRaw,FILTER_VALIDATE_FLOAT); $type=(string)($_POST['food_type']??''); $minutes=(int)($_POST['preparation_minutes']??10); $image=trim((string)($_POST['image']??'')); $qty=max(0,(int)($_POST['quantity']??0)); $minimum=max(0,(int)($_POST['minimum_quantity']??5));
        if (text_len($name)<2 || $category<1 || !preg_match('/^\d+(?:\.\d{1,2})?$/',$priceRaw) || $price===false || $price<=0 || $price>100000 || !in_array($type,['vegetarian','non_vegetarian'],true) || $minutes<1 || $minutes>240 || $qty>100000) throw new RuntimeException('Check the food name, category, price, food type, preparation time and stock.');
        $uploaded=$this->saveMenuUpload(); if ($uploaded!==null) $image=$uploaded;
        if ($image!=='' && !preg_match('~^(https://|http://|assets/uploads/menu/)[^\s<>]{1,490}$~i',$image)) throw new RuntimeException('Use an http(s) image URL or upload a JPG, PNG, WebP or GIF image.');
        $pdo->beginTransaction();
        if ($id) {
            $pdo->prepare('UPDATE menu_items SET category_id=?,name=?,description=?,price=?,food_type=?,image=?,preparation_minutes=?,is_available=? WHERE id=?')->execute([$category,$name,$description,$price,$type,$image?:null,$minutes,$qty>0?1:0,$id]);
            $s=$pdo->prepare('SELECT quantity,minimum_quantity FROM inventory WHERE menu_item_id=? FOR UPDATE'); $s->execute([$id]); $old=$s->fetch();
            if (!$old) throw new RuntimeException('Inventory record is missing for this food item.');
            if ((int)$old['quantity']!==$qty || (int)$old['minimum_quantity']!==$minimum) {
                $add=max(0,$qty-(int)$old['quantity']); $deduct=max(0,(int)$old['quantity']-$qty);
                $pdo->prepare('UPDATE inventory SET quantity=?,minimum_quantity=? WHERE menu_item_id=?')->execute([$qty,$minimum,$id]);
                $pdo->prepare('INSERT INTO stock_movements (menu_item_id,previous_quantity,added_quantity,deducted_quantity,updated_quantity,reason,admin_id) VALUES (?,?,?,?,?,?,?)')->execute([$id,$old['quantity'],$add,$deduct,$qty,'Menu item edit', (int)$_SESSION['user_id']]);
            }
        } else {
            $pdo->prepare('INSERT INTO menu_items (category_id,name,description,price,food_type,image,preparation_minutes,is_available) VALUES (?,?,?,?,?,?,?,?)')->execute([$category,$name,$description,$price,$type,$image?:null,$minutes,$qty>0?1:0]); $id=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO inventory (menu_item_id,quantity,minimum_quantity) VALUES (?,?,?)')->execute([$id,$qty,$minimum]);
            if ($qty>0) $pdo->prepare("INSERT INTO stock_movements (menu_item_id,previous_quantity,added_quantity,deducted_quantity,updated_quantity,reason,admin_id) VALUES (?,0,?,0,?,'Opening stock',?)")->execute([$id,$qty,$qty,(int)$_SESSION['user_id']]);
        }
        $pdo->commit();
    }

    private function saveMenuUpload(): ?string
    {
        if (empty($_FILES['image_file']['tmp_name']) || (int)($_FILES['image_file']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
        if ((int)$_FILES['image_file']['error']!==UPLOAD_ERR_OK || (int)$_FILES['image_file']['size']>3*1024*1024) throw new RuntimeException('Food images must upload successfully and be no larger than 3 MB.');
        $tmp=$_FILES['image_file']['tmp_name']; $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($tmp); $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($extensions[$mime]) || @getimagesize($tmp)===false) throw new RuntimeException('Upload a valid JPG, PNG, WebP or GIF image.');
        if (!is_dir(MENU_UPLOAD_PATH) && !mkdir(MENU_UPLOAD_PATH,0755,true) && !is_dir(MENU_UPLOAD_PATH)) throw new RuntimeException('Image storage is unavailable.');
        $name='food_'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
        if (!move_uploaded_file($tmp,MENU_UPLOAD_PATH.$name)) throw new RuntimeException('The food image could not be saved.');
        return 'assets/uploads/menu/'.$name;
    }

    private function adjustStock(PDO $pdo,int $adminId): void
    {
        $itemId=(int)($_POST['item_id']??0); $change=filter_var($_POST['quantity_change']??null,FILTER_VALIDATE_INT); $minimum=filter_var($_POST['minimum_quantity']??null,FILTER_VALIDATE_INT); $reason=trim((string)($_POST['reason']??'Stock adjustment'));
        if ($itemId<1 || $change===false || $change===0 || $minimum===false || $minimum<0 || text_len($reason)<2) throw new RuntimeException('Enter a non-zero stock adjustment, threshold and reason.');
        $pdo->beginTransaction(); $s=$pdo->prepare('SELECT quantity FROM inventory WHERE menu_item_id=? FOR UPDATE'); $s->execute([$itemId]); $old=$s->fetchColumn(); if ($old===false) throw new RuntimeException('Food item not found.');
        $new=(int)$old+(int)$change; if ($new<0 || $new>100000) throw new RuntimeException('Stock cannot be negative or exceed 100,000.');
        $pdo->prepare('UPDATE inventory SET quantity=?,minimum_quantity=? WHERE menu_item_id=?')->execute([$new,$minimum,$itemId]);
        $pdo->prepare('UPDATE menu_items SET is_available=? WHERE id=?')->execute([$new>0?1:0,$itemId]);
        $pdo->prepare('INSERT INTO stock_movements (menu_item_id,previous_quantity,added_quantity,deducted_quantity,updated_quantity,reason,admin_id) VALUES (?,?,?,?,?,?,?)')->execute([$itemId,$old,max(0,$change),max(0,-$change),$new,$reason,$adminId]);
        if ($new<=$minimum) { $s=$pdo->prepare('SELECT name FROM menu_items WHERE id=?'); $s->execute([$itemId]); $this->notifyAdmins($pdo,$new===0?'Food out of stock':'Low food stock',$s->fetchColumn().' now has '.$new.' portions.'); }
        $pdo->commit();
    }

    private function updateOrderStatus(PDO $pdo,int $adminId): void
    {
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??''); $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE'); $s->execute([$id]); $order=$s->fetch(); if (!$order) throw new RuntimeException('Order not found.');
        if ($status==='cancelled') {
            if (!in_array($order['status'],['pending','accepted'],true)) throw new RuntimeException('An order can only be cancelled before preparation starts.');
            $this->refundAndRestore($pdo,$order,'Admin cancellation');
        } else {
            $transitions=['pending'=>'accepted','accepted'=>'preparing','preparing'=>'ready','ready'=>'completed'];
            if (($transitions[$order['status']]??'')!==$status) throw new RuntimeException('That order status transition is not allowed.');
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status,$id]);
            $labels=['accepted'=>'accepted','preparing'=>'being prepared','ready'=>'ready for pickup','completed'=>'completed'];
            $this->notify($pdo,'student',(int)$order['user_id'],'Order '.$labels[$status],$order['order_number'].' is now '.$labels[$status].'.');
        }
        audit($adminId,'order-status',$order['order_number'].' → '.$status); $pdo->commit();
    }

    private function saveTable(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); $number=strtoupper(trim((string)($_POST['table_number']??''))); $capacity=(int)($_POST['capacity']??0); $enabled=isset($_POST['is_enabled'])?1:0;
        if (!preg_match('/^[A-Z0-9-]{1,30}$/',$number) || $capacity<1 || $capacity>30) throw new RuntimeException('Enter a table number and seating capacity between 1 and 30.');
        if ($id) $pdo->prepare('UPDATE canteen_tables SET table_number=?,capacity=?,is_enabled=? WHERE id=?')->execute([$number,$capacity,$enabled,$id]);
        else $pdo->prepare('INSERT INTO canteen_tables (table_number,capacity,is_enabled) VALUES (?,?,?)')->execute([$number,$capacity,$enabled]);
    }

    private function deleteTable(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); if($id<1) throw new RuntimeException('Choose a valid table.');
        $s=$pdo->prepare('SELECT COUNT(*) FROM table_reservations WHERE table_id=?'); $s->execute([$id]);
        if((int)$s->fetchColumn()>0) throw new RuntimeException('This table has reservation history. Disable it instead of deleting it.');
        $pdo->prepare('DELETE FROM canteen_tables WHERE id=?')->execute([$id]);
    }

    private function saveSlot(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); $label=trim((string)($_POST['label']??'')); $start=(string)($_POST['start_time']??''); $end=(string)($_POST['end_time']??''); $active=isset($_POST['is_active'])?1:0;
        if ($label==='' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end) || $end<=$start) throw new RuntimeException('Enter a label and a valid time range.');
        if ($id) $pdo->prepare('UPDATE reservation_slots SET label=?,start_time=?,end_time=?,is_active=? WHERE id=?')->execute([$label,$start,$end,$active,$id]);
        else $pdo->prepare('INSERT INTO reservation_slots (label,start_time,end_time,is_active) VALUES (?,?,?,?)')->execute([$label,$start,$end,$active]);
    }

    private function updateReservationStatus(PDO $pdo): void
    {
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??''); if (!in_array($status,['confirmed','rejected','cancelled','completed'],true)) throw new RuntimeException('Choose a valid reservation status.');
        $pdo->beginTransaction(); $s=$pdo->prepare('SELECT * FROM table_reservations WHERE id=? FOR UPDATE'); $s->execute([$id]); $r=$s->fetch(); if (!$r) throw new RuntimeException('Reservation not found.');
        if (in_array($r['status'],['cancelled','rejected','completed'],true)) throw new RuntimeException('This reservation is already closed.');
        $pdo->prepare('UPDATE table_reservations SET status=? WHERE id=?')->execute([$status,$id]);
        $msg=['confirmed'=>'confirmed','rejected'=>'rejected','cancelled'=>'cancelled','completed'=>'marked completed'][$status]; $this->notify($pdo,'student',(int)$r['user_id'],'Reservation '.$msg,'Your table reservation was '.$msg.'.');
        $pdo->commit();
    }

    private function saveOffer(PDO $pdo): void
    {
        $title=trim((string)($_POST['title']??'')); $description=trim((string)($_POST['description']??'')); $discount=(float)($_POST['discount_percent']??0); $start=str_replace('T',' ',(string)($_POST['starts_at']??'')); $end=str_replace('T',' ',(string)($_POST['ends_at']??''));
        if ($title==='' || $discount<0 || $discount>100 || strtotime($start)===false || strtotime($end)===false || strtotime($end)<=strtotime($start)) throw new RuntimeException('Enter an offer title, discount from 0–100, and valid dates.');
        $pdo->prepare('INSERT INTO offers (title,description,discount_percent,starts_at,ends_at,is_active) VALUES (?,?,?,?,?,1)')->execute([$title,$description,$discount,date('Y-m-d H:i:s',strtotime($start)),date('Y-m-d H:i:s',strtotime($end))]);
    }

    private function saveSettings(PDO $pdo): void
    {
        foreach(['canteen_name','reservation_policy'] as $key) {
            $value=trim((string)($_POST[$key]??'')); if ($value==='') throw new RuntimeException('Canteen name and reservation policy are required.');
            $pdo->prepare('INSERT INTO admin_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
        }
    }

    private function buildRecommendations(PDO $pdo,float $balance,int $limit): array
    {
        $s=$pdo->prepare('SELECT m.id,m.name,m.description,m.price,m.image,m.food_type,m.preparation_minutes,m.popularity,c.name category_name,i.quantity stock FROM menu_items m JOIN food_categories c ON c.id=m.category_id JOIN inventory i ON i.menu_item_id=m.id WHERE m.is_available=1 AND i.quantity>0 AND m.price<=? ORDER BY m.popularity DESC,(m.price/NULLIF(i.quantity,0)) ASC,m.name LIMIT 45');
        $s->execute([$balance]); $items=$s->fetchAll(); $singles=array_slice($items,0,$limit);
        $combos=[]; $n=count($items); $budget=max(1,$balance);
        for($i=0;$i<$n;$i++) for($j=$i+1;$j<$n;$j++) {
            $total=(float)$items[$i]['price']+(float)$items[$j]['price'];
            if ($total<=$balance) $combos[]=$this->combo([$items[$i],$items[$j]],$total,$budget);
            for($k=$j+1;$k<$n;$k++) { $sum=$total+(float)$items[$k]['price']; if($sum<=$balance) $combos[]=$this->combo([$items[$i],$items[$j],$items[$k]],$sum,$budget); }
        }
        usort($combos,function($a,$b){ if($a['score']===$b['score']) return $b['total']<=>$a['total']; return $b['score']<=>$a['score']; });
        $seen=[]; $unique=[]; foreach($combos as $c) { $key=implode('-',array_column($c['items'],'id')); if(isset($seen[$key])) continue; $seen[$key]=1; $unique[]=$c; if(count($unique)>=$limit) break; }
        return ['singles'=>$singles,'combos'=>$unique];
    }

    private function combo(array $items,float $total,float $budget): array
    {
        $pop=0; foreach($items as $i) $pop+=(int)$i['popularity'];
        $score=$pop/count($items)+($total/$budget)*20;
        return ['items'=>$items,'total'=>round($total,2),'remaining'=>round(max(0,$budget-$total),2),'savings'=>round(max(0,$budget-$total),2),'score'=>$score];
    }

    private function menuItems(PDO $pdo,array $filters): array
    {
        $sql='SELECT m.*,c.name category_name,i.quantity stock FROM menu_items m JOIN food_categories c ON c.id=m.category_id JOIN inventory i ON i.menu_item_id=m.id WHERE m.is_available=1 AND i.quantity>0'; $p=[];
        if($filters['q']!=='') { $sql.=' AND (m.name LIKE ? OR m.description LIKE ?)'; $p[]='%'.$filters['q'].'%'; $p[]='%'.$filters['q'].'%'; }
        if($filters['category']>0) { $sql.=' AND m.category_id=?'; $p[]=$filters['category']; }
        if(in_array($filters['food'],['vegetarian','non_vegetarian'],true)) { $sql.=' AND m.food_type=?'; $p[]=$filters['food']; }
        if($filters['max']!=='' && is_numeric($filters['max'])) { $sql.=' AND m.price<=?'; $p[]=(float)$filters['max']; }
        $sort=['price_asc'=>'m.price ASC','price_desc'=>'m.price DESC','popular'=>'m.popularity DESC,m.name','name'=>'m.name']; $sql.=' ORDER BY '.($sort[$filters['sort']]??$sort['popular']);
        $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchAll();
    }

    private function cartItems(PDO $pdo,int $uid): array
    {
        $s=$pdo->prepare('SELECT c.menu_item_id,c.quantity,m.name,m.price,m.image,m.is_available,i.quantity stock FROM cart_items c JOIN menu_items m ON m.id=c.menu_item_id JOIN inventory i ON i.menu_item_id=m.id WHERE c.user_id=? ORDER BY c.added_at DESC'); $s->execute([$uid]); return $s->fetchAll();
    }
    private function cartTotal(array $items): float { $sum=0.0; foreach($items as $i) $sum+=(float)$i['price']*(int)$i['quantity']; return round($sum,2); }
    private function activeOffer(PDO $pdo): ?array { $s=$pdo->query('SELECT * FROM offers WHERE is_active=1 AND starts_at<=NOW() AND ends_at>=NOW() ORDER BY discount_percent DESC,ends_at LIMIT 1'); return $s->fetch()?:null; }
    private function walletTransactions(PDO $pdo,int $uid): array { $s=$pdo->prepare('SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 100'); $s->execute([$uid]); return $s->fetchAll(); }
    private function paymentTransactions(PDO $pdo,int $uid): array { $s=$pdo->prepare('SELECT * FROM payment_transactions WHERE user_id=? ORDER BY id DESC LIMIT 100'); $s->execute([$uid]); return $s->fetchAll(); }
    private function adminMenuQuery(PDO $pdo,string $where=''): array
    {
        $sql='SELECT m.*,c.name category_name,i.quantity stock,i.minimum_quantity FROM menu_items m JOIN food_categories c ON c.id=m.category_id JOIN inventory i ON i.menu_item_id=m.id';
        if($where==='stock') $sql.=' WHERE i.quantity<=i.minimum_quantity'; $sql.=' ORDER BY c.sort_order,c.name,m.name'; return $pdo->query($sql)->fetchAll();
    }
    private function adminOrdersQuery(PDO $pdo,int $limit=250): array
    {
        return $pdo->query('SELECT o.*,u.full_name,u.student_id,(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) item_count FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT '.(int)$limit)->fetchAll();
    }
    private function notify(PDO $pdo,string $type,int $userId,string $title,string $message): void
    {
        $pdo->prepare('INSERT INTO notifications (recipient_type,user_id,title,message) VALUES (?,?,?,?)')->execute([$type,$userId,$title,$message]);
    }
    private function notifyAdmins(PDO $pdo,string $title,string $message): void
    {
        $admins=$pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll(); foreach($admins as $a) $this->notify($pdo,'admin',(int)$a['id'],$title,$message);
    }
    private function exportReport(): void
    {
        $kind=(string)($_GET['kind']??'sales'); $pdo=db(); $allowed=['sales'=>'SELECT o.order_number,u.student_id,u.full_name,o.total_amount,o.status,o.created_at FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC','inventory'=>'SELECT m.name,c.name category,i.quantity,i.minimum_quantity,m.is_available FROM inventory i JOIN menu_items m ON m.id=i.menu_item_id JOIN food_categories c ON c.id=m.category_id ORDER BY m.name','transactions'=>'SELECT wt.txn_ref,u.student_id,u.full_name,wt.amount,wt.type,wt.purpose,wt.status,wt.created_at FROM wallet_transactions wt JOIN users u ON u.id=wt.user_id ORDER BY wt.id DESC'];
        if(!isset($allowed[$kind])) { http_response_code(400); exit('Unknown report.'); }
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="canteen-'.$kind.'-'.date('Y-m-d').'.csv"');
        $out=fopen('php://output','w'); $stmt=$pdo->query($allowed[$kind]); $first=$stmt->fetch(PDO::FETCH_ASSOC); if($first) { fputcsv($out,array_keys($first)); fputcsv($out,array_values($first)); while($row=$stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($out,array_values($row)); } fclose($out); exit;
    }
}
