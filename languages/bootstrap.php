<?php
// Idioma de la interfaz; el dominio sigue determinando el mercado y la moneda.
$siteLanguages = ['es' => 'Español', 'eu' => 'Euskara', 'en' => 'English'];
$defaultLanguage = $domain === 'anatod.eus' ? 'eu' : 'es';
$requestedLanguage = $_GET['lang'] ?? null;
$savedLanguage = $_COOKIE['anatod-language'] ?? null;
$siteLanguage = is_string($requestedLanguage) && isset($siteLanguages[$requestedLanguage])
    ? $requestedLanguage
    : (is_string($savedLanguage) && isset($siteLanguages[$savedLanguage]) ? $savedLanguage : $defaultLanguage);

if (is_string($requestedLanguage) && isset($siteLanguages[$requestedLanguage])) {
    setcookie('anatod-language', $siteLanguage, [
        'expires' => time() + 365 * 24 * 60 * 60,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$spanishLocales = ['anatod.com.ar' => 'es-AR', 'anatod.com.mx' => 'es-MX'];
$siteLocales = ['es' => $spanishLocales[$domain] ?? 'es-ES', 'eu' => 'eu-ES', 'en' => 'en-GB'];
$siteLocale = $siteLocales[$siteLanguage];
// Las respuestas dependen del idioma guardado del visitante.
header('Content-Language: ' . $siteLanguage);
header('Vary: Cookie', false);
header('Cache-Control: private, no-cache');
$siteTranslations = [];
if ($siteLanguage !== 'es') {
    $catalogFile = __DIR__ . '/' . $siteLanguage . '.json';
    $catalog = is_readable($catalogFile) ? json_decode(file_get_contents($catalogFile), true) : [];
    $siteTranslations = is_array($catalog) ? $catalog : [];
}

if (!function_exists('_l')) {
    function _l($text) {
        global $siteLanguage, $siteTranslations;
        $text = (string) $text;
        // Los idiomas originales conservan sus textos sin traducir.
        if ($siteLanguage === 'es') {
            return $text;
        }
        $translation = $siteTranslations[$text] ?? null;
        return is_string($translation) && trim($translation) !== '' ? $translation : $text;
    }
}

// Solo se envían al navegador los textos usados por los scripts.
function anatodLanguagePayload() {
    global $siteLanguage, $siteLocale;
    $texts = [];
    $add = static function ($value) use (&$texts) {
        if (is_string($value) && $value !== '') {
            $texts[$value] = _l($value);
        }
    };
    $configFile = dirname(__DIR__) . '/assets/config/site.min.json';
    $raw = is_readable($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    if (is_array($raw)) {
        $regions = array_merge([$raw['defaults'] ?? []], array_values($raw['domains'] ?? []));
        foreach ($regions as $region) {
            foreach (['texts', 'messages'] as $group) {
                foreach (($region[$group] ?? []) as $key => $value) {
                    if ($key !== 'common.locale') {
                        $add($value);
                    }
                }
            }
            $add($region['country'] ?? '');
            $add($region['contact']['message'] ?? '');
            $add($region['contact']['label'] ?? '');
        }
        foreach (($raw['pricing']['currencies'] ?? []) as $label) {
            $add($label);
        }
    }
    foreach ([
        'Cambiar a modo claro', 'Cambiar a modo oscuro', 'Abrir menú', 'Cerrar menú',
        'No pudimos cargar la información de esta web. Vuelve a intentarlo.',
    ] as $text) {
        $add($text);
    }
    $texts['No pudimos cargar la información de esta web. Vuelve a intentarlo.'] = _l('No pudimos cargar la información de esta web. Vuelve a intentarlo.');
    return ['lang' => $siteLanguage, 'locale' => $siteLocale, 'texts' => (object) $texts];
}
