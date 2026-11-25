<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
if (($_SESSION['role']??'')!=='student') json_response(['ok'=>false,'error'=>'Sign in as a student to view tables.'],403);
$date=(string)($_GET['date']??''); $slot=(int)($_GET['slot']??0); $people=(int)($_GET['people']??0);
$d=DateTime::createFromFormat('Y-m-d',$date);
if (!$d||$d->format('Y-m-d')!==$date||$date<date('Y-m-d')||$slot<1||$people<1||$people>20) json_response(['ok'=>false,'error'=>'Choose a valid future date, time and party size.'],400);
$pdo=db(); $s=$pdo->prepare('SELECT start_time,end_time FROM reservation_slots WHERE id=? AND is_active=1'); $s->execute([$slot]); $time=$s->fetch();
if (!$time) json_response(['ok'=>false,'error'=>'Time slot is no longer active.'],404);
if($date===date('Y-m-d')&&$time['start_time']<=date('H:i:s')) json_response(['ok'=>false,'error'=>'That time slot has already started.'],400);
$q=$pdo->prepare("SELECT t.id,t.table_number,t.capacity,CASE WHEN t.is_enabled=0 THEN 'disabled' WHEN t.capacity<? THEN 'too_small' WHEN r.id IS NULL THEN 'available' ELSE 'booked' END availability FROM canteen_tables t LEFT JOIN table_reservations r ON r.table_id=t.id AND r.reservation_date=? AND r.status IN ('pending','confirmed') AND r.slot_id IN (SELECT s.id FROM reservation_slots s WHERE s.start_time<? AND s.end_time>?) ORDER BY t.table_number");
$q->execute([$people,$date,$time['end_time'],$time['start_time']]); json_response(['ok'=>true,'date'=>$date,'slot_id'=>$slot,'tables'=>$q->fetchAll()]);
