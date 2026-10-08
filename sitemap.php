<?php
$baseUrl = require __DIR__ . '/sitemap-origin.php';
if ($baseUrl === null) {
    http_response_code(400);
    exit;
}

$readItems = static function ($path) {
    if (!is_readable($path)) {
        return null;
    }
    $json = file_get_contents($path);
    if ($json === false) {
        return null;
    }
    $items = json_decode($json, true);
    if (!is_array($items) || ($items !== [] && array_keys($items) !== range(0, count($items) - 1))) {
        return null;
    }
    return $items;
};

$posts = $readItems(__DIR__ . '/blog/posts.min.json');
$events = $readItems(__DIR__ . '/events/eventos.min.json');

header('Content-Type: application/xml; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// No entregar un sitemap parcial si falta alguna fuente de contenido.
if ($posts === null || $events === null) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>' . "\n";
    exit;
}

$paths = [
    '/',
    '/about-us',
    '/software',
    '/contact-us',
    '/ispkeeper/fibra-optica/',
    '/ispkeeper/wisp/',
    '/ispkeeper/satelital/',
    '/ispkeeper/internet-prepago/',
    '/ispkeeper/telefonia-movil-fija-ip/',
    '/ispkeeper/tv/',
    '/ispkeeper/sepa/',
    '/ispkeeper/api/',
    '/kit-de-marca/',
    '/privacy-policy/',
    '/terms-conditions/',
    '/cookies-policy/',
    '/legal-notice/',
    '/blog/',
    '/events/',
];

foreach ($posts as $post) {
    if (!is_array($post)) {
        continue;
    }
    $date = $post['date'] ?? '';
    $slug = $post['slug'] ?? '';
    if (!is_string($date) || !is_string($slug)
        || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        || !preg_match('/^(?:[a-z0-9-]|%[0-9a-f]{2})+$/iD', $slug)) {
        continue;
    }
    // Conserva los slugs que ya contienen caracteres codificados como %xx.
    $paths[] = '/blog/' . str_replace('-', '/', $date) . '/' . $slug . '/';
}

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $slug = $event['slug'] ?? '';
    if (is_string($slug) && preg_match('/^[a-z0-9-]+$/D', $slug)) {
        $paths[] = '/blog/event/' . $slug . '/';
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_unique($paths) as $path) {
    $url = htmlspecialchars($baseUrl . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    echo '  <url><loc>' . $url . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";
