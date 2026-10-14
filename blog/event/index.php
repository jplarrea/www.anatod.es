<?php
require_once dirname(__DIR__, 2) . '/config.php';

$eventos = anatodContentItems('events');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$evento = anatodFindContent('events', $requestPath);
if ($eventos === null) {
    http_response_code(503);
} elseif (!$evento) {
    http_response_code(404);
} else {
    anatodRedirectContent('events', $evento, $requestPath);
    foreach (['title', 'detail'] as $field) {
        $evento[$field] = _l(anatodContentKey('events', $evento, $field), $evento[$field]);
    }
}

$escapar = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$titulo = $evento['title'] ?? _l('Evento no encontrado');
$detalle = $evento['detail'] ?? _l('No encontramos el evento solicitado.');
?>
<!doctype html>
<html lang="<?=htmlspecialchars($siteLanguage, ENT_QUOTES, 'UTF-8');?>" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escapar($titulo); ?> <?=htmlspecialchars(_l('· anatod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></title>
  <meta name="description" content="<?= $escapar($detalle); ?>">
  <?php require dirname(__DIR__, 2) . '/views/head.min.html'; ?>
  <style>
    .event-detail-photo { display: block; width: 100%; max-height: 540px; object-fit: cover; border: 1px solid var(--line); border-radius: 14px; }
  </style>
</head>
<body class="service-detail-page event-detail-page">
  <?php require dirname(__DIR__, 2) . '/views/header.min.html'; ?>
  <main id="contenido">
    <section class="service-detail-hero">
      <div class="container">
        <nav class="breadcrumb-link" aria-label="<?=htmlspecialchars(_l('Ruta de navegación'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">
          <a href="<?=anatodSeoEscape(anatodPageUrl('/'));?>"><?=htmlspecialchars(_l('anatod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></a><span aria-hidden="true">/</span><a href="<?=anatodSeoEscape(anatodPageUrl('/events/'));?>"><?=htmlspecialchars(_l('Eventos'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></a><span aria-hidden="true">/</span><span><?= $escapar($titulo); ?></span>
        </nav>
        <?php if ($evento): ?>
        <div class="row g-5 align-items-center">
          <div class="col-lg-6">
            <div class="eyebrow"><?=htmlspecialchars(_l('ENCUENTROS DEL SECTOR'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></div>
            <h1><?= $escapar($titulo); ?></h1>
            <p class="hero-description"><?= $escapar($detalle); ?></p>
            <a class="text-link" href="<?=anatodSeoEscape(anatodPageUrl('/events/'));?>"><?=htmlspecialchars(_l('Volver a todos los eventos'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?> <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          </div>
          <div class="col-lg-6">
            <img class="event-detail-photo" src="<?= $escapar($evento['photo'] ?? ''); ?>" alt="<?= $escapar($titulo); ?>" fetchpriority="high">
          </div>
        </div>
        <?php else: ?>
        <div class="row">
          <div class="col-lg-8">
            <div class="eyebrow"><?=htmlspecialchars(_l('EVENTOS'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></div>
            <h1><?= $escapar($titulo); ?></h1>
            <p class="hero-description"><?= $escapar($detalle); ?></p>
            <a class="text-link" href="<?=anatodSeoEscape(anatodPageUrl('/events/'));?>"><?=htmlspecialchars(_l('Volver a todos los eventos'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?> <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <?php require dirname(__DIR__, 2) . '/views/footer.min.html'; ?>
</body>
</html>
