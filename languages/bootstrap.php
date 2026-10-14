<?php
require_once __DIR__ . '/catalog.php';
// Los dominios regionales tienen un idioma fijo. Las selecciones se resuelven
// en el dominio de destino. El idioma depende de la URL, nunca de cookies.
$languageSettings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
$languageDefinitions = $languageSettings['languages'];
$siteLanguages = [];
foreach ($languageDefinitions as $languageCode => $definition) {
    $siteLanguages[$languageCode] = $definition['name'];
}
$requestedLanguage = $_GET['lang'] ?? null;
$validRequest = is_string($requestedLanguage) && isset($languageDefinitions[$requestedLanguage]);

// Un cambio explícito de idioma siempre lleva a su dominio: eu -> .eus;
// es, en y los idiomas futuros -> .com. Se mantienen ruta y parámetros.
if ($validRequest && $domain !== $languageDefinitions[$requestedLanguage]['domain']) {
    $targetDomain = $languageDefinitions[$requestedLanguage]['domain'];
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestPath = is_string($requestPath) && substr($requestPath, 0, 1) === '/' ? $requestPath : '/';
    $requestPath = str_replace(["\r", "\n"], '', $requestPath);
    $redirectParameters = $_GET;
    if ($targetDomain === $languageSettings['multilingualDomain'] && $requestedLanguage !== 'es') {
        $redirectParameters['lang'] = $requestedLanguage;
    } else {
        unset($redirectParameters['lang']);
    }
    $redirectQuery = http_build_query($redirectParameters, '', '&', PHP_QUERY_RFC3986);
    header('Cache-Control: private, no-store');
    header('Location: https://www.' . $targetDomain . $requestPath . ($redirectQuery !== '' ? '?' . $redirectQuery : ''), true, 302);
    exit;
}

$siteLanguage = $languageSettings['fixedDomains'][$domain] ?? $languageSettings['defaultLanguage'];
if ($domain === $languageSettings['multilingualDomain']) {
    $siteLanguage = $validRequest ? $requestedLanguage : $siteLanguage;
}

$spanishLocales = ['anatod.com.ar' => 'es-AR', 'anatod.com.mx' => 'es-MX'];
$siteLocale = $siteLanguage === 'es' && isset($spanishLocales[$domain])
    ? $spanishLocales[$domain]
    : $languageDefinitions[$siteLanguage]['locale'];
// La misma URL siempre entrega el mismo idioma a visitantes y buscadores.
header('Content-Language: ' . $siteLanguage);
header('Cache-Control: private, no-cache');
$siteTranslations = [];
if ($siteLanguage !== 'es') {
    $siteTranslations = anatodLoadCatalog($siteLanguage);
}

if (!function_exists('_l')) {
    function _l($text, $fallback = null) {
        global $siteLanguage, $siteTranslations;
        $text = (string) $text;
        $original = $fallback === null ? $text : (string) $fallback;
        // Los idiomas originales conservan sus textos sin traducir.
        if ($siteLanguage === 'es') {
            return $original;
        }
        $translation = $siteTranslations[$text] ?? ($siteTranslations[$original] ?? null);
        return is_string($translation) && trim($translation) !== '' ? $translation : $original;
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
