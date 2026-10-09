<?php
// Los dominios regionales tienen un idioma fijo. Las selecciones se resuelven
// en el dominio de destino, donde se guarda la preferencia del visitante.
$languageSettings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
$languageDefinitions = $languageSettings['languages'];
$siteLanguages = [];
foreach ($languageDefinitions as $languageCode => $definition) {
    $siteLanguages[$languageCode] = $definition['name'];
}
$requestedLanguage = $_GET['lang'] ?? null;
$savedLanguage = $_COOKIE['anatod-language'] ?? null;
$validRequest = is_string($requestedLanguage) && isset($languageDefinitions[$requestedLanguage]);

// Un cambio explícito de idioma siempre lleva a su dominio: eu -> .eus;
// es, en y los idiomas futuros -> .com. Se mantienen ruta y parámetros.
if ($validRequest && $domain !== $languageDefinitions[$requestedLanguage]['domain']) {
    $targetDomain = $languageDefinitions[$requestedLanguage]['domain'];
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestPath = is_string($requestPath) && substr($requestPath, 0, 1) === '/' ? $requestPath : '/';
    $requestPath = str_replace(["\r", "\n"], '', $requestPath);
    $redirectParameters = $_GET;
    if ($targetDomain === $languageSettings['multilingualDomain']) {
        $redirectParameters['lang'] = $requestedLanguage;
    } else {
        unset($redirectParameters['lang']);
    }
    $redirectQuery = http_build_query($redirectParameters, '', '&', PHP_QUERY_RFC3986);
    header('Cache-Control: private, no-store');
    header('Location: https://' . $targetDomain . $requestPath . ($redirectQuery !== '' ? '?' . $redirectQuery : ''), true, 302);
    exit;
}

$siteLanguage = $languageSettings['fixedDomains'][$domain] ?? $languageSettings['defaultLanguage'];
if ($domain === $languageSettings['multilingualDomain']) {
    // Las cookies antiguas de euskera no pueden activar ese idioma en .com.
    $validSavedLanguage = is_string($savedLanguage) && isset($languageDefinitions[$savedLanguage]) &&
        $languageDefinitions[$savedLanguage]['domain'] === $domain;
    $siteLanguage = $validRequest ? $requestedLanguage : ($validSavedLanguage ? $savedLanguage : $siteLanguage);
    if ($validRequest) {
        setcookie('anatod-language', $siteLanguage, [
            'expires' => time() + 365 * 24 * 60 * 60,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

$spanishLocales = ['anatod.com.ar' => 'es-AR', 'anatod.com.mx' => 'es-MX'];
$siteLocale = $siteLanguage === 'es' && isset($spanishLocales[$domain])
    ? $spanishLocales[$domain]
    : $languageDefinitions[$siteLanguage]['locale'];
// Las respuestas dependen del idioma guardado del visitante.
header('Content-Language: ' . $siteLanguage);
header('Vary: Cookie', false);
header('Cache-Control: private, no-cache');
$siteTranslations = [];
if ($siteLanguage !== 'es') {
    $catalogFile = __DIR__ . '/' . $siteLanguage . '.min.json';
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
    global $siteLanguage, $siteLocale, $languageSettings;
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
    return [
        'lang' => $siteLanguage,
        'locale' => $siteLocale,
        'languages' => array_keys($languageSettings['languages']),
        'fixedDomains' => $languageSettings['fixedDomains'],
        'multilingualDomain' => $languageSettings['multilingualDomain'],
        'texts' => (object) $texts,
    ];
}
