<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
if(empty($_SESSION['user_id'])) json_response(['ok'=>false,'error'=>'Unauthorized'],403);
$id=(int)($_GET['id']??0); if($id<1) json_response(['ok'=>false,'error'=>'Invalid order.'],400);
$pdo=db();
if(($_SESSION['role']??'')==='student') { $s=$pdo->prepare('SELECT id,order_number,status,updated_at FROM orders WHERE id=? AND user_id=?'); $s->execute([$id,(int)$_SESSION['user_id']]); }
elseif(($_SESSION['role']??'')==='admin') { $s=$pdo->prepare('SELECT id,order_number,status,updated_at FROM orders WHERE id=?'); $s->execute([$id]); }
else json_response(['ok'=>false,'error'=>'Unauthorized'],403);
$row=$s->fetch(); if(!$row) json_response(['ok'=>false,'error'=>'Order not found.'],404); json_response(['ok'=>true,'data'=>$row]);
