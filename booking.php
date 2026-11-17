<?php
require __DIR__.'/config.php';
$roomId = filter_input(INPUT_GET,'room',FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST,'room_id',FILTER_VALIDATE_INT);
$stmt=db()->prepare('SELECT r.*,c.name category_name FROM rooms r JOIN categories c ON c.id=r.category_id WHERE r.id=? AND r.is_active=1');
$stmt->execute([$roomId]); $room=$stmt->fetch();
if(!$room){http_response_code(404);exit('Номер не найден. Вернитесь в <a href="index.php">каталог</a>.');}
$error=''; $success=false;
$blocked=db()->prepare("SELECT check_in,check_out FROM bookings WHERE room_id=? AND status='approved' AND check_out > CURDATE() ORDER BY check_in");
$blocked->execute([$roomId]); $blockedDates=$blocked->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 $first=trim((string)($_POST['first_name']??'')); $last=trim((string)($_POST['last_name']??''));
 $phone=trim((string)($_POST['phone']??'')); $email=trim((string)($_POST['email']??''));
 $in=trim((string)($_POST['check_in']??'')); $out=trim((string)($_POST['check_out']??''));
 $namePattern='/^[\p{Cyrillic}\s.\-]+$/u';
 // Даты гостиницы проверяем по московскому времени.
 $hotelTimezone = new DateTimeZone('Europe/Moscow');
 $today = (new DateTimeImmutable('today', $hotelTimezone))->format('Y-m-d');
 $dateIn = DateTimeImmutable::createFromFormat('!Y-m-d', $in, $hotelTimezone);
 $dateInErrors = DateTimeImmutable::getLastErrors();
 $dateOut = DateTimeImmutable::createFromFormat('!Y-m-d', $out, $hotelTimezone);
 $dateOutErrors = DateTimeImmutable::getLastErrors();
 $dateInInvalid = !$dateIn || $dateIn->format('Y-m-d') !== $in
     || ($dateInErrors !== false && ($dateInErrors['warning_count'] > 0 || $dateInErrors['error_count'] > 0));
 $dateOutInvalid = !$dateOut || $dateOut->format('Y-m-d') !== $out
     || ($dateOutErrors !== false && ($dateOutErrors['warning_count'] > 0 || $dateOutErrors['error_count'] > 0));

 // Разрешены: +7(999)123-45-67, 8 999 123 45 67 и 89991234567.
 $phoneCompact = preg_replace('/\s+/u', '', $phone);
 $phoneValid = (bool)preg_match('/^\+7\(\d{3}\)\d{3}-\d{2}-\d{2}$/', $phone)
     || (bool)preg_match('/^8\d{10}$/', $phoneCompact)
     || (bool)preg_match('/^8 \d{3} \d{3} \d{2} \d{2}$/u', $phone);

 $phoneDigits = preg_replace('/\D+/', '', $phone);
 $phoneForDb = (strlen($phoneDigits) === 11 && $phoneDigits[0] === '8')
     ? '+7(' . substr($phoneDigits, 1, 3) . ')' . substr($phoneDigits, 4, 3) . '-' . substr($phoneDigits, 7, 2) . '-' . substr($phoneDigits, 9, 2)
     : $phone;

 if($first===''||!preg_match($namePattern,$first)) $error='Введите имя кириллицей. Допускаются пробелы, точки и тире.';
 elseif($last===''||!preg_match($namePattern,$last)) $error='Введите фамилию кириллицей. Допускаются пробелы, точки и тире.';
 elseif(!$phoneValid) $error='Введите телефон в одном из форматов: +7(999)123-45-67, 8 999 123 45 67 или 89991234567.';
 elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Введите корректный адрес электронной почты.';
 elseif($dateInInvalid || $dateOutInvalid) $error='Укажите корректные даты заезда и выезда.';
 elseif($in < $today) $error='Дата заезда не может быть раньше сегодняшней (по московскому времени).';
 elseif($out <= $in) $error='Дата выезда должна быть позже даты заезда.';
 else {
   $overlap=db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status='approved' AND check_in < ? AND check_out > ?");
   $overlap->execute([$roomId,$out,$in]);
   if((int)$overlap->fetchColumn()>0) $error='На выбранный период номер уже забронирован. Выберите другие даты.';
   else {
    $ins=db()->prepare("INSERT INTO bookings(room_id,first_name,last_name,phone,email,check_in,check_out,status) VALUES(?,?,?,?,?,?,?,'pending')");
    $ins->execute([$roomId,$first,$last,$phoneForDb,$email,$in,$out]); $success=true;
   }
 }
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Бронирование — Сладкие сны</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/site.css"></head><body class="container">
<header class="site-header d-flex justify-content-between align-items-center"><a class="brand" href="index.php">Сладкие сны</a><a href="index.php" class="btn btn-outline-secondary btn-sm">← Каталог</a></header>
<main class="form-panel my-4"><h1 class="h2">Бронирование номера</h1><p><strong><?=e($room['name'])?></strong> · <?=e($room['category_name'])?> · <?=number_format((float)$room['price'],0,',',' ')?> ₽/сутки</p>
<?php if($success): ?><div class="alert alert-success">Заявка успешно отправлена! После рассмотрения администратором вы сможете узнать статус бронирования по обратной связи.</div><a class="btn btn-primary" href="index.php">Вернуться в каталог</a>
<?php else: ?>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if($blockedDates): ?><div class="alert alert-warning"><strong>Уже одобренные периоды:</strong><ul class="mb-0"><?php foreach($blockedDates as $d): ?><li><?=e(date('d.m.Y',strtotime($d['check_in'])))?> — <?=e(date('d.m.Y',strtotime($d['check_out'])))?></li><?php endforeach; ?></ul>Эти даты недоступны для бронирования.</div><?php endif; ?>
<form method="post" class="row g-3" novalidate><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="room_id" value="<?=e($room['id'])?>">
<div class="col-md-6"><label class="form-label">Имя *</label><input class="form-control" name="first_name" required value="<?=e($_POST['first_name']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Фамилия *</label><input class="form-control" name="last_name" required value="<?=e($_POST['last_name']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Телефон *</label><input class="form-control" name="phone" type="tel" autocomplete="tel" placeholder="+7(999)123-45-67 или 8 999 123 45 67" required value="<?=e($_POST['phone']??'')?>"><div class="form-text">Допустимо: +7(999)123-45-67, 8 999 123 45 67 или 89991234567.</div></div>
<div class="col-md-6"><label class="form-label">E-mail *</label><input class="form-control" type="email" name="email" required value="<?=e($_POST['email']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Дата заезда *</label><input class="form-control" type="date" name="check_in" min="<?=e((new DateTimeImmutable('today', new DateTimeZone('Europe/Moscow')))->format('Y-m-d'))?>" required value="<?=e($_POST['check_in']??'')?>"></div>
<div class="col-md-6"><label class="form-label">Дата выезда *</label><input class="form-control" type="date" name="check_out" min="<?=e((new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d'))?>" required value="<?=e($_POST['check_out']??'')?>"></div>
<div class="col-12"><button class="btn btn-success btn-lg">Отправить заявку</button></div></form>
<?php endif; ?></main><footer>Москва, ул. Ивовая, 48 · Пн–Пт, 8:00–17:00 · 8 (800) 555-35-35</footer>
<script>
const ci=document.querySelector('[name=check_in]'),co=document.querySelector('[name=check_out]');
if(ci&&co){
  const today=ci.min;
  ci.addEventListener('change',()=>{
    if(ci.value && ci.value<today){ci.setCustomValidity('Дата заезда не может быть раньше сегодняшней.');ci.reportValidity();ci.value='';return;}
    ci.setCustomValidity('');
    if(ci.value){
      const [y,m,d]=ci.value.split('-').map(Number);
      const next=new Date(y,m-1,d); next.setDate(next.getDate()+1);
      const minOut=[next.getFullYear(),String(next.getMonth()+1).padStart(2,'0'),String(next.getDate()).padStart(2,'0')].join('-');
      co.min=minOut;
      if(co.value && co.value<minOut) co.value='';
    } else { co.min=today; }
  });
  co.addEventListener('change',()=>{
    co.setCustomValidity(ci.value && co.value<=ci.value ? 'Дата выезда должна быть позже даты заезда.' : '');
  });
}
</script></body></html>
