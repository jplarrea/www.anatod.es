<?php
$baseUrl = require __DIR__ . '/sitemap-origin.php';
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
if ($baseUrl === null) {
    http_response_code(400);
    exit;
}

echo "User-agent: *\nAllow: /\n\n";
echo 'Sitemap: ' . $baseUrl . '/sitemap.xml' . "\n";
