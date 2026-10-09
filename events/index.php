<?php
require_once dirname(__DIR__) . '/config.php';

$eventos = json_decode(file_get_contents(__DIR__ . '/eventos.min.json'), true);
if (!is_array($eventos)) {
    $eventos = [];
}

foreach ($eventos as &$translatedEvent) {
    foreach (['title', 'detail'] as $field) {
        if (isset($translatedEvent[$field]) && is_string($translatedEvent[$field])) {
            $translatedEvent[$field] = _l($translatedEvent[$field]);
        }
    }
}
unset($translatedEvent);

$escapar = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!doctype html>
<html lang="<?=htmlspecialchars($siteLanguage, ENT_QUOTES, 'UTF-8');?>" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=htmlspecialchars(_l('Eventos de anatod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></title>
  <meta name="description" content="<?=htmlspecialchars(_l('Encuentros de anatod con el sector de las telecomunicaciones.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">
  <?php require dirname(__DIR__) . '/views/head.min.html'; ?>
  <style>
    .event-card-image { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border: 1px solid var(--line); border-radius: 10px; }
  </style>
</head>
<body class="service-detail-page events-page">
  <?php require dirname(__DIR__) . '/views/header.min.html'; ?>
  <main id="contenido">
    <section class="service-detail-hero">
      <div class="container">
        <a class="breadcrumb-link" href="/"><span><?=htmlspecialchars(_l('anatod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span><span aria-hidden="true">/</span><span><?=htmlspecialchars(_l('Eventos'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span></a>
        <div class="row g-5 align-items-center">
          <div class="col-lg-8">
            <div class="eyebrow"><?=htmlspecialchars(_l('ENCUENTROS DEL SECTOR'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></div>
            <h1><?=htmlspecialchars(_l('Eventos'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?><br><span><?=htmlspecialchars(_l('anatod.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></span></h1>
            <p class="hero-description"><?=htmlspecialchars(_l('Compartimos experiencias y novedades con operadores y empresas de telecomunicaciones en España y Latinoamérica.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
          </div>
        </div>
      </div>
    </section>
    <section class="section service-detail-content" aria-label="<?=htmlspecialchars(_l('Eventos de anatod'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>">
      <div class="container">
        <div class="row g-4">
          <?php foreach ($eventos as $evento): ?>
          <div class="col-md-6 col-lg-4">
            <article class="service-card h-100">
              <img class="event-card-image mb-4" src="<?= $escapar($evento['photo'] ?? ''); ?>" alt="<?= $escapar($evento['title'] ?? ''); ?>" loading="lazy">
              <h2 class="h3"><a href="/blog/event/<?= $escapar($evento['slug'] ?? ''); ?>/"><?= $escapar($evento['title'] ?? ''); ?></a></h2>
              <p><?= $escapar($evento['detail'] ?? ''); ?></p>
              <a class="text-link mt-auto" href="/blog/event/<?= $escapar($evento['slug'] ?? ''); ?>/">
                <?=htmlspecialchars(_l('Ver evento'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?> <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg>
              </a>
            </article>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>
  <?php require dirname(__DIR__) . '/views/footer.min.html'; ?>
</body>
</html>
