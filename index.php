<?php
require __DIR__.'/config.php';
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$categories = db()->query('SELECT id,name FROM categories ORDER BY id')->fetchAll();
if ($category) {
    $q = db()->prepare('SELECT r.*, c.name AS category_name FROM rooms r JOIN categories c ON c.id=r.category_id WHERE r.is_active=1 AND c.id=? ORDER BY RAND() LIMIT 6');
    $q->execute([$category]);
} else {
    $q = db()->query('SELECT r.*, c.name AS category_name FROM rooms r JOIN categories c ON c.id=r.category_id WHERE r.is_active=1 ORDER BY RAND() LIMIT 6');
}
$rooms = $q->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Сладкие сны — каталог номеров</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/site.css"></head>
<body class="container">
<header class="site-header d-flex flex-wrap align-items-center justify-content-between gap-2"><a class="brand" href="index.php">Сладкие сны</a><span class="muted">Приезжайте как гости, уезжайте как друзья!</span><nav><a class="btn btn-outline-secondary btn-sm" href="login.php">Вход администратора</a></nav></header>
<section class="hero"><h1>Каталог номеров</h1><p class="mb-0">Выберите номер для комфортного отдыха. Цены указаны за сутки.</p></section>
<form class="panel p-3 mb-4 d-flex flex-wrap align-items-end gap-2" method="get">
<div><label class="form-label" for="category">Категория номера</label><select class="form-select" id="category" name="category"><option value="0">Все категории</option><?php foreach($categories as $c): ?><option value="<?=e($c['id'])?>" <?=$category===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?></select></div>
<button class="btn btn-primary">Применить</button><a class="btn btn-outline-secondary" href="index.php">Сбросить фильтр</a>
</form>
<?php if (!$rooms): ?><div class="alert alert-info">Номера этой категории пока не добавлены.</div><?php else: ?><div class="row g-4"><?php foreach($rooms as $r): $features=array_filter(array_map('trim',explode(';',(string)$r['description']))); ?>
<div class="col-md-6 col-lg-4"><article class="card room-card"><img src="img/<?=e($r['image'])?>" class="card-img-top" alt="<?=e($r['name'])?>"><div class="card-body"><span class="badge text-bg-secondary align-self-start"><?=e($r['category_name'])?></span><h2 class="h4 mt-2"><?=e($r['name'])?></h2><div class="fs-5 fw-bold mb-2"><?=number_format((float)$r['price'],0,',',' ')?> ₽ <small class="muted fs-6">/ сутки</small></div><h3 class="h6">Характеристики</h3><ul><?php foreach($features as $f): ?><li><?=e($f)?></li><?php endforeach; ?></ul><a class="btn btn-success" href="booking.php?room=<?=e($r['id'])?>">Забронировать</a></div></article></div>
<?php endforeach; ?></div><?php endif; ?>
<footer class="d-flex flex-wrap justify-content-between gap-3"><span>Москва, ул. Ивовая, 48</span><span>Пн–Пт, 8:00–17:00</span><a href="tel:88005553535">8 (800) 555-35-35</a><a href="mailto:обращения@СветлыеСны.рф">обращения@СветлыеСны.рф</a></footer>
<script src="js/bootstrap.bundle.min.js"></script></body></html>
