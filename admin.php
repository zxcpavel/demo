<?php
require __DIR__.'/config.php';require_admin();$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$id=filter_input(INPUT_POST,'booking_id',FILTER_VALIDATE_INT);$action=(string)($_POST['action']??'');
 if($id&&$action==='delete'){$s=db()->prepare('DELETE FROM bookings WHERE id=?');$s->execute([$id]);$msg='Заявка удалена.';}
 elseif($id&&in_array($action,['approve','reject'],true)){
  $s=db()->prepare('SELECT * FROM bookings WHERE id=?');$s->execute([$id]);$b=$s->fetch();
  if(!$b)$msg='Заявка не найдена.';
  elseif($action==='approve'){
   $x=db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND id<>? AND status='approved' AND check_in < ? AND check_out > ?");
   $x->execute([$b['room_id'],$id,$b['check_out'],$b['check_in']]);
   if((int)$x->fetchColumn()>0)$msg='Нельзя одобрить: период пересекается с другой подтверждённой бронью.';
   else{$u=db()->prepare("UPDATE bookings SET status='approved' WHERE id=?");$u->execute([$id]);$msg='Заявка одобрена.';}
  } else {$u=db()->prepare("UPDATE bookings SET status='rejected' WHERE id=?");$u->execute([$id]);$msg='Заявка отклонена.';}
 }
}
$rows=db()->query('SELECT b.*,r.name room_name,r.room_number,c.name category_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN categories c ON c.id=r.category_id ORDER BY b.created_at DESC')->fetchAll();
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Панель администратора</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/site.css"></head><body class="container"><header class="site-header d-flex justify-content-between align-items-center"><a class="brand" href="index.php">Сладкие сны</a><div><a href="index.php" class="btn btn-outline-secondary btn-sm">На сайт</a> <a href="logout.php" class="btn btn-outline-danger btn-sm">Выйти</a></div></header><main><h1 class="h2 my-4">Заявки на бронирование</h1><?php if($msg):?><div class="alert alert-info"><?=e($msg)?></div><?php endif;?><div class="panel p-3"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Гость</th><th>Контакты</th><th>Номер</th><th>Период</th><th>Статус</th><th>Действия</th></tr></thead><tbody><?php foreach($rows as $b):?><tr><td><?=e($b['last_name'].' '.$b['first_name'])?></td><td><?=e($b['phone'])?><br><?=e($b['email'])?></td><td><?=e($b['room_name'])?><br><small><?=e($b['category_name'])?></small></td><td><?=e(date('d.m.Y',strtotime($b['check_in'])))?>–<?=e(date('d.m.Y',strtotime($b['check_out'])))?></td><td><span class="status status-<?=e($b['status'])?>"><?=e(status_label($b['status']))?></span></td><td><form method="post" class="d-flex flex-wrap gap-1"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="booking_id" value="<?=e($b['id'])?>"><?php if($b['status']==='pending'):?><button name="action" value="approve" class="btn btn-success btn-sm">Одобрить</button><button name="action" value="reject" class="btn btn-warning btn-sm">Отклонить</button><?php endif;?><button name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Удалить заявку?')">Удалить</button></form></td></tr><?php endforeach;?></tbody></table></div><?php if(!$rows):?><p class="muted mb-0">Заявок пока нет.</p><?php endif;?></div></main></body></html>
