<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log',dirname(__DIR__).'/logs/php_error.log');

require_once dirname(__DIR__).'/config/constants.php';
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/config/functions.php';
send_security_headers();
if (is_file(dirname(__DIR__).'/vendor/autoload.php')) require_once dirname(__DIR__).'/vendor/autoload.php';

$action=(string)($_GET['action']??'home');
// Clear stale sessions from the former parent/canteen roles before routing.
if (!empty($_SESSION['user_id']) && !in_array($_SESSION['role'] ?? '', ['student','admin'], true)) {
    $_SESSION=[];
    session_regenerate_id(true);
}
if (!empty($_SESSION['user_id']) && !empty($_SESSION['force_change']) && !in_array($action,['change-password','logout'],true)) redirect(url('index.php?action=change-password'));

if ($action==='login' || $action==='logout' || $action==='forgot-password' || $action==='reset-password' || $action==='change-password') {
    require_once dirname(__DIR__).'/controllers/AuthController.php'; $auth=new AuthController();
    if($action==='login') $auth->login(); elseif($action==='logout') $auth->logout(); elseif($action==='forgot-password') $auth->forgotPassword(); elseif($action==='reset-password') $auth->resetPassword(); else $auth->changePassword();
    exit;
}
if ($action==='register' || $action==='admin' || strpos($action,'admin/')===0 || strpos($action,'student/')===0) {
    require_once dirname(__DIR__).'/controllers/CollegeController.php'; (new CollegeController())->handle($action); exit;
}
if ($action==='home') {
    if(!empty($_SESSION['user_id'])) redirect(url('index.php?action='.(($_SESSION['role']??'')==='admin'?'admin/dashboard':'student/dashboard')));
    view('home',['pageTitle'=>APP_NAME]); exit;
}
http_response_code(404);
view('errors/404',['pageTitle'=>'Page not found']);
