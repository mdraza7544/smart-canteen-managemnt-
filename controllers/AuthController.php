<?php
declare(strict_types=1);

class AuthController
{
    public function login(): void
    {
        if (!empty($_SESSION['user_id']) && !in_array($_SESSION['role'] ?? '', ['student','admin'], true)) {
            $_SESSION=[];
            session_regenerate_id(true);
        }
        if (!empty($_SESSION['user_id']) && empty($_SESSION['force_change'])) $this->redirectByRole($_SESSION['role'] ?? '');
        $role=(string)($_GET['role']??$_POST['role']??'student'); $identifier=''; $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $role=(string)($_POST['role']??'student'); $identifier=trim((string)($_POST['identifier']??'')); $password=(string)($_POST['password']??'');
            if (!csrf_check()) $error='Your session expired. Reload the page and try again.';
            elseif (!in_array($role,['admin','student'],true)) $error='Choose a valid account type.';
            elseif ($identifier===''||$password==='') $error='Enter your email or student ID and password.';
            else {
                $pdo=db();
                if ($role==='student') $stmt=$pdo->prepare("SELECT * FROM users WHERE role='student' AND is_active=1 AND (email=? OR student_id=?) LIMIT 1");
                else $stmt=$pdo->prepare("SELECT * FROM users WHERE role='admin' AND is_active=1 AND email=? LIMIT 1");
                $role==='student'?$stmt->execute([$identifier,$identifier]):$stmt->execute([$identifier]); $user=$stmt->fetch();
                if (!$user||!password_verify($password,$user['password'])) $error='The login details did not match an active account.';
                else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']=(int)$user['id']; $_SESSION['role']=$user['role']; $_SESSION['name']=$user['full_name'];
                    $_SESSION['username']=$user['student_id']?:$user['email']; $_SESSION['force_change']=(int)$user['force_password_change'];
                    $pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
                    if ((int)$user['force_password_change']===1) { flash_set('info','Please set a new password to continue.'); redirect(url('index.php?action=change-password')); }
                    flash_set('success','Welcome back, '.$user['full_name'].'!'); $this->redirectByRole($user['role']);
                }
            }
        }
        view('auth/login',['pageTitle'=>'Sign in','error'=>$error,'identifier'=>$identifier,'needsHashReset'=>false,'flashes'=>flash_get()]);
    }

    public function logout(): void
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_check()) { flash_set('error','Please sign out from the account menu.'); redirect(url('index.php?action=login')); }
        $_SESSION=[];
        if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); }
        session_destroy(); session_start(); session_regenerate_id(true); flash_set('info','You have been signed out.'); redirect(url('index.php?action=login'));
    }

    public function changePassword(): void
    {
        require_login(); $error=''; $success='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $current=(string)($_POST['current_password']??''); $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??'');
            if (!csrf_check()) $error='Invalid security token.';
            elseif (strlen($new)<8) $error='Use at least 8 characters for your new password.';
            elseif ($new!==$confirm) $error='The new passwords do not match.';
            else {
                $pdo=db(); $s=$pdo->prepare('SELECT password FROM users WHERE id=?'); $s->execute([(int)$_SESSION['user_id']]); $hash=$s->fetchColumn();
                if (!$hash||!password_verify($current,(string)$hash)) $error='Current password is incorrect.';
                else { $pdo->prepare('UPDATE users SET password=?,force_password_change=0 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),(int)$_SESSION['user_id']]); $_SESSION['force_change']=0; flash_set('success','Password updated.'); $this->redirectByRole($_SESSION['role']); }
            }
        }
        render_with_layout('auth/change-password',['pageTitle'=>'Change password','error'=>$error,'success'=>$success,'forced'=>!empty($_SESSION['force_change'])],'blank');
    }

    public function forgotPassword(): void
    {
        $error=''; $sent=false;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            if (!csrf_check()) $error='Invalid security token.';
            else { $email=strtolower(trim((string)($_POST['email']??''))); if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Enter the email address on your account.'; else { $this->requestReset($email); $sent=true; } }
        }
        render_with_layout('auth/forgot-password',['pageTitle'=>'Forgot password','error'=>$error,'sent'=>$sent],'blank');
    }

    public function resetPassword(): void
    {
        $token=(string)($_GET['token']??$_POST['token']??''); $error=''; $reset=null;
        if ($token!=='') { $s=db()->prepare('SELECT * FROM password_resets WHERE token=? AND expires_at>NOW() ORDER BY id DESC LIMIT 1'); $s->execute([$token]); $reset=$s->fetch(); }
        if (!$reset) $error='This password reset link is invalid or has expired.';
        elseif ($_SERVER['REQUEST_METHOD']==='POST') {
            $new=(string)($_POST['new_password']??''); $confirm=(string)($_POST['confirm_password']??'');
            if (!csrf_check()) $error='Invalid security token.';
            elseif (strlen($new)<8) $error='Use at least 8 characters for your new password.';
            elseif ($new!==$confirm) $error='The passwords do not match.';
            else { $pdo=db(); $pdo->prepare('UPDATE users SET password=?,force_password_change=0 WHERE email=?')->execute([password_hash($new,PASSWORD_DEFAULT),$reset['email']]); $pdo->prepare('DELETE FROM password_resets WHERE email=?')->execute([$reset['email']]); flash_set('success','Password reset. Sign in with your new password.'); redirect(url('index.php?action=login')); }
        }
        render_with_layout('auth/reset-password',['pageTitle'=>'Reset password','error'=>$error,'token'=>$token],'blank');
    }

    private function requestReset(string $email): void
    {
        $pdo=db(); $s=$pdo->prepare('SELECT id FROM users WHERE email=? AND is_active=1'); $s->execute([$email]);
        if (!$s->fetch()) return;
        $token=bin2hex(random_bytes(24)); $expires=date('Y-m-d H:i:s',time()+3600);
        $pdo->prepare('DELETE FROM password_resets WHERE email=?')->execute([$email]); $pdo->prepare('INSERT INTO password_resets (email,token,expires_at) VALUES (?,?,?)')->execute([$email,$token,$expires]);
        // XAMPP demo mode records the reset URL in logs/mail.log; configure SMTP before sending mail.
        $line='['.date('Y-m-d H:i:s').'] Password reset for '.$email.': '.url('index.php?action=reset-password&token='.$token).PHP_EOL;
        if (!is_dir(LOG_PATH)) @mkdir(LOG_PATH,0755,true); @file_put_contents(LOG_PATH.'mail.log',$line,FILE_APPEND);
    }

    private function redirectByRole(string $role): void
    {
        if ($role==='admin') redirect(url('index.php?action=admin/dashboard'));
        if ($role==='student') redirect(url('index.php?action=student/dashboard'));
        redirect(url('index.php?action=login'));
    }
}
