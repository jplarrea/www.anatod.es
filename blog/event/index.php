<?php
require_once dirname(__DIR__, 2) . '/config.php';

$eventos = json_decode(file_get_contents(dirname(__DIR__, 2) . '/events/eventos.min.json'), true);
$eventos = is_array($eventos) ? $eventos : [];
$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? $_GET['slug'] : '';
$slug = preg_match('/^[a-z0-9-]+$/', $slug) ? $slug : '';
$evento = null;

foreach ($eventos as $candidato) {
    if (($candidato['slug'] ?? '') === $slug) {
        $evento = $candidato;
        break;
    }
}

if (!$evento) {
    http_response_code(404);
}

$escapar = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$titulo = $evento['title'] ?? 'Evento no encontrado';
$detalle = $evento['detail'] ?? 'No encontramos el evento solicitado.';
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $escapar($titulo); ?> · anatod</title>
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
        <nav class="breadcrumb-link" aria-label="Ruta de navegación">
          <a href="/">anatod</a><span aria-hidden="true">/</span><a href="/events/">Eventos</a><span aria-hidden="true">/</span><span><?= $escapar($titulo); ?></span>
        </nav>
        <?php if ($evento): ?>
        <div class="row g-5 align-items-center">
          <div class="col-lg-6">
            <div class="eyebrow">ENCUENTROS DEL SECTOR</div>
            <h1><?= $escapar($titulo); ?></h1>
            <p class="hero-description"><?= $escapar($detalle); ?></p>
            <a class="text-link" href="/events/">Volver a todos los eventos <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          </div>
          <div class="col-lg-6">
            <img class="event-detail-photo" src="<?= $escapar($evento['photo'] ?? ''); ?>" alt="<?= $escapar($titulo); ?>" fetchpriority="high">
          </div>
        </div>
        <?php else: ?>
        <div class="row">
          <div class="col-lg-8">
            <div class="eyebrow">EVENTOS</div>
            <h1><?= $escapar($titulo); ?></h1>
            <p class="hero-description"><?= $escapar($detalle); ?></p>
            <a class="text-link" href="/events/">Volver a todos los eventos <svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <?php require dirname(__DIR__, 2) . '/views/footer.min.html'; ?>
</body>
</html>
