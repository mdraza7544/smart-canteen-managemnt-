<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
if (($_SESSION['role']??'')!=='student') json_response(['ok'=>false,'error'=>'Unauthorized'],403);
if (!csrf_check()) json_response(['ok'=>false,'error'=>'Invalid security token.'],400);
$in=json_input(); if(!$in) $in=$_POST; $item=(int)($in['item_id']??0); $quantity=(int)($in['quantity']??0); $uid=(int)$_SESSION['user_id'];
if($item<1||$quantity<0||$quantity>20) json_response(['ok'=>false,'error'=>'Choose a quantity between 0 and 20.'],400);
$pdo=db();
try {
 if($quantity===0) $pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND menu_item_id=?')->execute([$uid,$item]);
 else {
  $pdo->beginTransaction(); $s=$pdo->prepare('SELECT m.is_available,i.quantity FROM menu_items m JOIN inventory i ON i.menu_item_id=m.id WHERE m.id=? FOR UPDATE'); $s->execute([$item]); $row=$s->fetch();
  if(!$row||!(int)$row['is_available']||$quantity>(int)$row['quantity']) throw new RuntimeException('Item unavailable or stock is too low.');
  $pdo->prepare('INSERT INTO cart_items (user_id,menu_item_id,quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),added_at=NOW()')->execute([$uid,$item,$quantity]); $pdo->commit();
 }
 $s=$pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id=?'); $s->execute([$uid]); json_response(['ok'=>true,'cart_count'=>(int)$s->fetchColumn()]);
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); json_response(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'Could not update cart.'],400); }
