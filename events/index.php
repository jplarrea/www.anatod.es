<?php
require_once dirname(__DIR__) . '/config.php';

$eventos = json_decode(file_get_contents(__DIR__ . '/eventos.min.json'), true);
if (!is_array($eventos)) {
    $eventos = [];
}

$escapar = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Eventos de anatod</title>
  <meta name="description" content="Encuentros de anatod con el sector de las telecomunicaciones.">
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
        <a class="breadcrumb-link" href="/"><span>anatod</span><span aria-hidden="true">/</span><span>Eventos</span></a>
        <div class="row g-5 align-items-center">
          <div class="col-lg-8">
            <div class="eyebrow">ENCUENTROS DEL SECTOR</div>
            <h1>Eventos<br><span>anatod.</span></h1>
            <p class="hero-description">Compartimos experiencias y novedades con operadores y empresas de telecomunicaciones en España y Latinoamérica.</p>
          </div>
        </div>
      </div>
    </section>
    <section class="section service-detail-content" aria-label="Eventos de anatod">
      <div class="container">
        <div class="row g-4">
          <?php foreach ($eventos as $evento): ?>
          <div class="col-md-6 col-lg-4">
            <article class="service-card h-100">
              <img class="event-card-image mb-4" src="<?= $escapar($evento['photo'] ?? ''); ?>" alt="<?= $escapar($evento['title'] ?? ''); ?>" loading="lazy">
              <h2 class="h3"><?= $escapar($evento['title'] ?? ''); ?></h2>
              <p><?= $escapar($evento['detail'] ?? ''); ?></p>
              <a class="text-link mt-auto" href="/blog/event/<?= $escapar($evento['slug'] ?? ''); ?>/">
                Ver evento <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg>
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
