<?php
require_once __DIR__ . '/seo.php';
$baseUrl = require __DIR__ . '/sitemap-origin.php';
if ($baseUrl === null) {
    http_response_code(400);
    exit;
}

$posts = anatodContentItems('blog');
$events = anatodContentItems('events');

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
    $paths[] = anatodContentPath('blog', $post);
}

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $slug = $event['slug'] ?? '';
    if (is_string($slug) && preg_match('/^[a-z0-9-]+$/D', $slug)) {
        $paths[] = anatodContentPath('events', $event);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
$host = preg_replace('/^www\./', '', parse_url($baseUrl, PHP_URL_HOST));
$languages = $host === 'anatod.com' ? ['es', 'en'] : ($host === 'anatod.eus' ? ['eu'] : ['es']);
foreach (array_unique($paths) as $path) {
    foreach ($languages as $language) {
        if (!anatodSeoTranslated($path, $language)) {
            continue;
        }
        $url = htmlspecialchars(anatodSeoUrl($host, $path, $language), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        echo '  <url><loc>' . $url . '</loc>' . "\n";
        foreach (anatodSeoAlternates($path) as $locale => $alternate) {
            echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($locale, ENT_XML1 | ENT_QUOTES, 'UTF-8') .
                '" href="' . htmlspecialchars($alternate, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>' . "\n";
        }
        echo '  </url>' . "\n";
    }
}
echo '</urlset>' . "\n";
