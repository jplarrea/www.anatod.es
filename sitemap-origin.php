<?php
// Conserva el dominio y la variante con/sin www usados en la petición.
$sitemapHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$sitemapHost = preg_replace('/:[0-9]+$/D', '', $sitemapHost);

// Solo los dominios del sitio pueden aparecer en las URLs generadas.
if (!preg_match('/^(?:www\.)?anatod\.(?:com\.ar|com\.mx|com|es)$/D', $sitemapHost)) {
    return null;
}

return 'https://' . $sitemapHost;
