<?php
declare(strict_types=1);
/** PDO connection for the existing MVC-lite application. XAMPP defaults are configurable here. */
function db(): PDO
{
    static $pdo=null;
    if($pdo===null) {
        $host=getenv('SMEIC_DB_HOST')?:'127.0.0.1'; $dbname=getenv('SMEIC_DB_NAME')?:'smeic_college_canteen'; $user=getenv('SMEIC_DB_USER')?:'root'; $pass=getenv('SMEIC_DB_PASS')?:'';
        try {
            $pdo=new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch(PDOException $e) {
            $dir=dirname(__DIR__).'/logs/'; if(!is_dir($dir)) @mkdir($dir,0755,true); error_log('['.date('Y-m-d H:i:s').'] DB connection: '.$e->getMessage().PHP_EOL,3,$dir.'error.log');
            http_response_code(500); echo '<h2>Database connection failed</h2><p>Start MySQL and import the Smart College Canteen schema from <code>database.sql</code>. Check the credentials in <code>config/database.php</code>.</p>'; exit;
        }
    }
    return $pdo;
}
