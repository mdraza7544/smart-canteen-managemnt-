<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
$role=(string)($_SESSION['role']??''); $uid=(int)($_SESSION['user_id']??0);
if(!$uid||!in_array($role,['student','admin'],true)) json_response(['ok'=>false,'error'=>'Unauthorized'],403);
if(!csrf_check()) json_response(['ok'=>false,'error'=>'Invalid security token.'],400);
$in=json_input()?:$_POST; $id=(int)($in['id']??0); $pdo=db();
if($id>0) $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND recipient_type=? AND user_id=?')->execute([$id,$role,$uid]);
else $pdo->prepare('UPDATE notifications SET is_read=1 WHERE recipient_type=? AND user_id=?')->execute([$role,$uid]);
json_response(['ok'=>true]);
