<?php
require_once dirname(__DIR__) . '/config.php';

$escape = static function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$postsFile = __DIR__ . '/posts.min.json';
$posts = is_readable($postsFile) ? json_decode(file_get_contents($postsFile), true) : null;
if (!is_array($posts)) {
    http_response_code(503);
    $posts = [];
    $blogUnavailable = true;
} else {
    $blogUnavailable = false;
}
// Los slugs, fechas, enlaces e imágenes conservan su identidad.
foreach ($posts as &$translatedPost) {
    foreach (['title', 'excerpt', 'content_html'] as $field) {
        if (isset($translatedPost[$field]) && is_string($translatedPost[$field])) {
            $translatedPost[$field] = _l($translatedPost[$field]);
        }
    }
    if (isset($translatedPost['image']['alt'])) {
        $translatedPost['image']['alt'] = _l($translatedPost['image']['alt']);
    }
}
unset($translatedPost);

usort($posts, static function ($a, $b) {
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});
$postUrl = static function ($post) {
    return '/blog/' . str_replace('-', '/', $post['date']) . '/' . $post['slug'] . '/';
};

// REQUEST_URI conserva la ruta original aunque Apache haga la reescritura.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/blog/', PHP_URL_PATH);
$requestPath = '/' . trim(rawurldecode((string) $requestPath), '/') . '/';
$isListing = $requestPath === '/blog/';
$post = null;
if (!$isListing) {
    foreach ($posts as $candidate) {
        if (rawurldecode($postUrl($candidate)) === $requestPath) {
            $post = $candidate;
            break;
        }
    }
    if (!$post && !$blogUnavailable) {
        http_response_code(404);
    }
}

if ($post && in_array($domain ?? '', ['anatod.es', 'anatod.eus'], true)) {
    // Evita que los contenidos externos contacten con terceros antes del permiso.
    $iframePattern = <<<'REGEX'
/(<iframe\b(?:"[^"]*"|'[^']*'|[^'">])*?)(?<![-\w])src\s*=/i
REGEX;
    $post['content_html'] = preg_replace($iframePattern, '$1 hidden data-consent-src=', $post['content_html']);
}

$title = $blogUnavailable ? _l('Publicaciones no disponibles') : ($isListing ? _l('Novedades') : ($post['title'] ?? _l('Publicación no encontrada')));
$description = $blogUnavailable ? _l('Las publicaciones no están disponibles en este momento.') : ($isListing ? _l('Todas las novedades de anatod.') : ($post['excerpt'] ?? _l('No encontramos la publicación solicitada.')));
$previousPosts = $post ? array_values(array_filter($posts, static function ($candidate) use ($post) {
    return $candidate['slug'] !== $post['slug'];
})) : [];
$previousPosts = array_slice($previousPosts, 0, 5);

$postsPerPage = 6;
$totalPages = max(1, (int) ceil(count($posts) / $postsPerPage));
$requestedPage = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentPage = min($totalPages, $requestedPage === false ? 1 : $requestedPage);
$pagePosts = array_slice($posts, ($currentPage - 1) * $postsPerPage, $postsPerPage);
$pageUrl = static function ($page) {
    return $page === 1 ? '/blog/' : '/blog/?pagina=' . $page;
};
$formatDate = static function ($value) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date) {
        return '';
    }
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $date->format('j') . ' ' . _l($months[(int) $date->format('n') - 1]) . ', ' . $date->format('Y');
};

require dirname(__DIR__) . '/views/blog.min.html';
