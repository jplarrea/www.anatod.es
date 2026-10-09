<?php
require_once 'config.php';

$clientsFile = __DIR__ . '/assets/config/clients.min.json';
$clientsData = is_readable($clientsFile)
    ? json_decode(file_get_contents($clientsFile), true)
    : [];
$clients = [];
if (is_array($clientsData)) {
    foreach ($clientsData as $client) {
        if (!is_array($client)) {
            continue;
        }
        foreach (['nombre', 'localidad', 'pais', 'logo'] as $field) {
            if (!isset($client[$field]) || !is_string($client[$field]) || trim($client[$field]) === '') {
                continue 2;
            }
            $client[$field] = trim($client[$field]);
        }
        // Acepta logos externos por HTTPS, incluidos los alojados en un CDN.
        $isExternalLogo = filter_var($client['logo'], FILTER_VALIDATE_URL) !== false &&
            strtolower((string) parse_url($client['logo'], PHP_URL_SCHEME)) === 'https';
        $isLocalLogo = preg_match('~^assets/img/clients/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|svg)$~i', $client['logo']) &&
            is_file(__DIR__ . '/' . $client['logo']);
        if (!$isExternalLogo && !$isLocalLogo) {
            continue;
        }
        // La web es opcional; solo se enlazan direcciones HTTP o HTTPS válidas.
        $client['web'] = isset($client['web']) && is_string($client['web']) ? trim($client['web']) : '';
        if (filter_var($client['web'], FILTER_VALIDATE_URL) === false ||
            !in_array(strtolower((string) parse_url($client['web'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $client['web'] = '';
        }
        $clients[] = $client;
    }
}
// Mezcla antes de limitar para no favorecer a los primeros del archivo.
shuffle($clients);
$clients = array_slice($clients, 0, 6);

$clientEscape = static function ($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

require_once 'views/index.min.html';
