<?php
/**
 * Optional, repeatable importer from the original SMEIC school database.
 * Run after importing database.sql. The legacy database is read only and left intact.
 * Example: C:\xampp\php\php.exe migrations\001_college_migration.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
$host=getenv('SMEIC_DB_HOST')?:'127.0.0.1'; $dbUser=getenv('SMEIC_DB_USER')?:'root'; $dbPass=getenv('SMEIC_DB_PASS')?:'';
$sourceName=getenv('SMEIC_LEGACY_DB_NAME')?:'smeic_canteen'; $targetName=getenv('SMEIC_DB_NAME')?:'smeic_college_canteen';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
try {
    $source=new PDO("mysql:host={$host};dbname={$sourceName};charset=utf8mb4",$dbUser,$dbPass,$options);
    $target=new PDO("mysql:host={$host};dbname={$targetName};charset=utf8mb4",$dbUser,$dbPass,$options);
} catch(Throwable $e) { fwrite(STDERR,"Database connection failed: ".$e->getMessage()."\nImport database.sql first and check the database credentials.\n"); exit(1); }

function hasTable(PDO $pdo,string $table): bool { $s=$pdo->prepare('SHOW TABLES LIKE ?'); $s->execute([$table]); return (bool)$s->fetchColumn(); }
foreach(['students','menu_items','orders','order_items'] as $required) if(!hasTable($source,$required)) { fwrite(STDERR,"Legacy database is missing table {$required}.\n"); exit(1); }
foreach(['users','wallets','food_categories','menu_items','inventory','orders','order_items','wallet_transactions','notifications'] as $required) if(!hasTable($target,$required)) { fwrite(STDERR,"Target database is missing {$required}. Import database.sql first.\n"); exit(1); }
$target->exec("CREATE TABLE IF NOT EXISTS migration_imports (source_key VARCHAR(100) PRIMARY KEY, imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$mark=$target->prepare('INSERT IGNORE INTO migration_imports (source_key) VALUES (?)');
$isImported=function(string $key) use($target): bool { $s=$target->prepare('SELECT 1 FROM migration_imports WHERE source_key=?'); $s->execute([$key]); return (bool)$s->fetchColumn(); };
$userMap=[]; $studentRows=$source->query('SELECT * FROM students WHERE COALESCE(is_deleted,0)=0 ORDER BY id')->fetchAll();
$getTarget=$target->prepare('SELECT id FROM users WHERE student_id=? AND role=\'student\' LIMIT 1');
$insertUser=$target->prepare("INSERT INTO users (role,full_name,email,phone,student_id,department,password,force_password_change,is_active) VALUES ('student',?,?,?,?,?,?,1,1)");
$insertWallet=$target->prepare('INSERT IGNORE INTO wallets (user_id,balance) VALUES (?,0.00)');
foreach($studentRows as $student) {
    $getTarget->execute([$student['student_id']]); $targetUserId=(int)($getTarget->fetchColumn()?:0);
    if(!$targetUserId) {
        $sid=(string)$student['student_id']; $email=strtolower($sid).'@legacy.college';
        $insertUser->execute([$student['full_name'],$email,null,$sid,'Imported college member',$student['password']]); $targetUserId=(int)$target->lastInsertId();
    }
    $userMap[(int)$student['id']]=$targetUserId; $insertWallet->execute([$targetUserId]);
    $wallet=$source->prepare('SELECT balance FROM wallets WHERE student_id=?'); $wallet->execute([(int)$student['id']]); $oldBalance=$wallet->fetchColumn();
    if($oldBalance!==false && (float)$oldBalance>0 && !$isImported('wallet-seed:'.$student['id'])) {
        $target->beginTransaction();
        $target->prepare('UPDATE wallets SET balance=? WHERE user_id=?')->execute([number_format((float)$oldBalance,2,'.',''),$targetUserId]);
        $target->prepare("INSERT INTO wallet_transactions (user_id,amount,type,purpose,reference,txn_ref,balance_after,status) VALUES (?,?,'credit','admin_adjustment','Imported legacy wallet balance',? ,?,'success')")
            ->execute([$targetUserId,$oldBalance,'MIG-BAL-'.$student['id'].'-'.bin2hex(random_bytes(3)),$oldBalance]);
        $mark->execute(['wallet-seed:'.$student['id']]); $target->commit();
    }
}

$categoryMap=['snacks'=>'Snacks','drinks'=>'Beverages','desserts'=>'Desserts','veg'=>'Lunch','nonveg'=>'Lunch']; $menuMap=[];
$findCategory=$target->prepare('SELECT id FROM food_categories WHERE name=?'); $createCategory=$target->prepare('INSERT IGNORE INTO food_categories (name,is_active) VALUES (?,1)');
$findMenu=$target->prepare('SELECT id FROM menu_items WHERE name=? LIMIT 1');
$oldMenu=$source->query('SELECT * FROM menu_items ORDER BY id')->fetchAll();
foreach($oldMenu as $item) {
    $oldCat=strtolower((string)($item['category']??'veg')); $categoryName=$categoryMap[$oldCat]??'Lunch';
    $createCategory->execute([$categoryName]); $findCategory->execute([$categoryName]); $categoryId=(int)$findCategory->fetchColumn();
    $findMenu->execute([$item['name']]); $newItemId=(int)($findMenu->fetchColumn()?:0);
    if(!$newItemId) {
        $type=$oldCat==='nonveg'?'non_vegetarian':'vegetarian'; $image=empty($item['image'])?null:'assets/uploads/menu/'.basename((string)$item['image']);
        $target->prepare('INSERT INTO menu_items (category_id,name,description,price,food_type,image,is_available) VALUES (?,?,?,?,?,?,?)')->execute([$categoryId,$item['name'],$item['description']??'',(float)$item['price'],$type,$image,(int)($item['is_available']??1)]);
        $newItemId=(int)$target->lastInsertId(); $target->prepare('INSERT INTO inventory (menu_item_id,quantity,minimum_quantity) VALUES (?,50,5)')->execute([$newItemId]);
    }
    $menuMap[(int)$item['id']]=$newItemId;
}

$orderMap=[]; $oldOrders=$source->query('SELECT * FROM orders ORDER BY id')->fetchAll();
foreach($oldOrders as $order) {
    $legacyStudentId=(int)($order['student_id']??0); if(!isset($userMap[$legacyStudentId])) continue;
    $lookup=$target->prepare('SELECT id FROM orders WHERE order_number=?'); $lookup->execute([$order['order_number']]); $newOrderId=(int)($lookup->fetchColumn()?:0);
    if(!$newOrderId) {
        $oldStatus=(string)$order['status']; $status=$oldStatus==='picked_up'?'completed':(in_array($oldStatus,['pending','accepted','preparing','ready','completed','cancelled'],true)?$oldStatus:'completed');
        $token=preg_match('/^\d{6}$/',(string)($order['pickup_token']??''))?(string)$order['pickup_token']:(string)random_int(100000,999999);
        $total=(float)$order['total_amount']; $target->prepare('INSERT INTO orders (user_id,order_number,pickup_token,subtotal,discount_percent,discount_amount,total_amount,status,created_at,updated_at) VALUES (?,?,?,?,0,0,?,?,?,?)')
            ->execute([$userMap[$legacyStudentId],$order['order_number'],$token,$total,$total,$status,$order['created_at']??date('Y-m-d H:i:s'),$order['updated_at']??$order['created_at']??date('Y-m-d H:i:s')]);
        $newOrderId=(int)$target->lastInsertId();
    }
    $orderMap[(int)$order['id']]=$newOrderId;
}
$oldLines=$source->query('SELECT * FROM order_items ORDER BY id')->fetchAll();
foreach($oldLines as $line) {
    $oldOrder=(int)$line['order_id']; $oldItem=(int)$line['menu_item_id']; if(!isset($orderMap[$oldOrder],$menuMap[$oldItem])) continue;
    $exists=$target->prepare('SELECT 1 FROM order_items WHERE order_id=? AND menu_item_id=? LIMIT 1'); $exists->execute([$orderMap[$oldOrder],$menuMap[$oldItem]]); if($exists->fetchColumn()) continue;
    $name=$target->prepare('SELECT name FROM menu_items WHERE id=?'); $name->execute([$menuMap[$oldItem]]);
    $target->prepare('INSERT INTO order_items (order_id,menu_item_id,item_name,quantity,unit_price) VALUES (?,?,?,?,?)')->execute([$orderMap[$oldOrder],$menuMap[$oldItem],$name->fetchColumn(),(int)$line['quantity'],(float)$line['price']]);
}

if(hasTable($source,'transactions')) {
    $oldTransactions=$source->query("SELECT * FROM transactions WHERE user_type='student' ORDER BY id")->fetchAll();
    foreach($oldTransactions as $tx) {
        $oldId=(int)$tx['user_id']; if(!isset($userMap[$oldId])||$isImported('wallet-transaction:'.$tx['id'])) continue;
        $wallet=$target->prepare('SELECT balance FROM wallets WHERE user_id=?'); $wallet->execute([$userMap[$oldId]]); $balance=(float)$wallet->fetchColumn();
        $type=$tx['type']==='debit'?'debit':'credit'; $amount=(float)$tx['amount']; $purpose=$type==='debit'?'order_payment':'admin_adjustment'; $after=$tx['balance_after']!==null?(float)$tx['balance_after']:$balance;
        $target->prepare('INSERT INTO wallet_transactions (user_id,amount,type,purpose,reference,txn_ref,balance_after,status,created_at) VALUES (?,?,?,?,?,?,?,\'success\',?)')->execute([$userMap[$oldId],$amount,$type,$purpose,$tx['reference']??'Imported legacy activity','MIG-TX-'.$tx['id'].'-'.bin2hex(random_bytes(3)),$after,$tx['created_at']??date('Y-m-d H:i:s')]);
        $mark->execute(['wallet-transaction:'.$tx['id']]);
    }
}
if(hasTable($source,'notifications')) {
    $oldNotes=$source->query("SELECT * FROM notifications WHERE user_type='student' ORDER BY id")->fetchAll();
    foreach($oldNotes as $note) {
        $oldId=(int)$note['user_id']; if(!isset($userMap[$oldId])||$isImported('notification:'.$note['id'])) continue;
        $target->prepare("INSERT INTO notifications (recipient_type,user_id,title,message,is_read,created_at) VALUES ('student',?,?,?,?,?)")->execute([$userMap[$oldId],$note['title'],$note['message']??'',(int)$note['is_read'],$note['created_at']??date('Y-m-d H:i:s')]);
        $mark->execute(['notification:'.$note['id']]);
    }
}
fwrite(STDOUT,'Legacy import finished. Students mapped: '.count($userMap).'; menu items mapped: '.count($menuMap).'; orders mapped: '.count($orderMap).".\n");
fwrite(STDOUT,"Parent and canteen accounts were not imported. The source database remains unchanged. Imported students should change their password after sign-in.\n");
