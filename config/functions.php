<?php
declare(strict_types=1);
if (session_status()===PHP_SESSION_NONE) {
    session_name(SESSION_NAME); $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((int)($_SERVER['SERVER_PORT']??0)===443);
    session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$secure]); session_start();
}
function send_security_headers(): void
{
    if(headers_sent()) return;
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}
function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function text_len(string $value): int { if(function_exists('mb_strlen')) return mb_strlen($value,'UTF-8'); $count=preg_match_all('/./us',$value,$matches); return $count===false?strlen($value):$count; }
function redirect(string $target): void { header('Location: '.$target); exit; }
function url(string $path=''): string { return BASE_URL.'/'.ltrim($path,'/'); }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; }
function csrf_check(): bool { $value=$_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??''); return is_string($value)&&isset($_SESSION['csrf_token'])&&hash_equals($_SESSION['csrf_token'],$value); }
function flash_set(string $type,string $message): void { $_SESSION['flash'][]=['type'=>$type,'msg'=>$message]; }
function flash_get(): array { $messages=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $messages; }
function current_user(): ?array
{
    if(empty($_SESSION['user_id'])) return null;
    return ['user_id'=>(int)$_SESSION['user_id'],'role'=>$_SESSION['role']??null,'name'=>$_SESSION['name']??null,'username'=>$_SESSION['username']??null,'force_change'=>$_SESSION['force_change']??0];
}
function require_login(): void { if(empty($_SESSION['user_id'])) { flash_set('error','Please sign in to continue.'); redirect(url('index.php?action=login')); } }
function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        flash_set('error', 'You do not have access to that page.');
        redirect(url('index.php?action=login'));
    }
    if (!empty($_SESSION['force_change'])) {
        flash_set('info', 'Set a new password before continuing.');
        redirect(url('index.php?action=change-password'));
    }
}
function clean_str($value): string { return trim((string)filter_var((string)$value,FILTER_SANITIZE_SPECIAL_CHARS)); }
function money($amount): string { return CURRENCY.number_format((float)$amount,2); }
function dt_fmt(?string $value,string $format='d M Y, h:i A'): string { if(!$value) return '-'; $time=strtotime($value); return $time?date($format,$time):'-'; }
function app_log(string $message): void { if(!is_dir(LOG_PATH)) @mkdir(LOG_PATH,0755,true); error_log('['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL,3,LOG_PATH.'app.log'); }
function audit(int $adminId,string $action,string $details=''): void
{
    try { db()->prepare('INSERT INTO audit_logs (admin_id,action,details) VALUES (?,?,?)')->execute([$adminId,$action,$details]); }
    catch(Throwable $e) { app_log('Audit log failed: '.$e->getMessage()); }
}
function view(string $path,array $data=[]): void
{
    extract($data,EXTR_SKIP); $file=dirname(__DIR__).'/views/'.ltrim($path,'/').'.php';
    if(!is_file($file)) { http_response_code(404); echo '<h2>Page not found</h2>'; return; }
    require $file;
}
function render_with_layout(string $viewPath,array $data=[],string $layout='main'): void
{
    extract($data,EXTR_SKIP); $header=dirname(__DIR__).'/views/layouts/header.php'; $sidebar=dirname(__DIR__).'/views/layouts/sidebar.php'; $content=dirname(__DIR__).'/views/'.ltrim($viewPath,'/').'.php'; $footer=dirname(__DIR__).'/views/layouts/footer.php';
    if($layout==='blank') { if(is_file($content)) require $content; else { http_response_code(404); echo '<div class="p-6">Page not found</div>'; } return; }
    if(is_file($header)) require $header; if(is_file($sidebar)) require $sidebar; if(is_file($content)) require $content; else echo '<div class="p-6">Page not found</div>'; if(is_file($footer)) require $footer;
}
function json_response(array $data,int $status=200): void { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE); exit; }
function json_input(): array { $body=file_get_contents('php://input'); if(!$body) return []; $data=json_decode($body,true); return is_array($data)?$data:[]; }
