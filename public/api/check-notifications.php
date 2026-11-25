<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
$role=(string)($_SESSION['role']??''); $uid=(int)($_SESSION['user_id']??0);
if(!$uid||!in_array($role,['student','admin'],true)) json_response(['ok'=>false,'error'=>'Unauthorized'],403);
$pdo=db(); $s=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_type=? AND user_id=? AND is_read=0'); $s->execute([$role,$uid]); $unread=(int)$s->fetchColumn();
$s=$pdo->prepare('SELECT id,title,message,created_at,is_read FROM notifications WHERE recipient_type=? AND user_id=? ORDER BY id DESC LIMIT 1'); $s->execute([$role,$uid]);
json_response(['ok'=>true,'data'=>['unread'=>$unread,'latest'=>$s->fetch()?:null]]);
