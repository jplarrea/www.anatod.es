<?php
// Identidad y rutas compartidas por publicaciones y eventos.
function anatodContentItems($kind) {
    static $cache = [];
    if (array_key_exists($kind, $cache)) {
        return $cache[$kind];
    }
    $files = ['blog' => 'blog/posts', 'events' => 'events/eventos'];
    if (!isset($files[$kind])) {
        return null;
    }
    $read = static function ($path) {
        $data = is_readable($path) ? json_decode(file_get_contents($path), true) : null;
        return is_array($data) && ($data === [] || array_keys($data) === range(0, count($data) - 1)) ? $data : null;
    };
    $source = $read(__DIR__ . '/' . $files[$kind] . '.json');
    $served = $read(__DIR__ . '/' . $files[$kind] . '.min.json');
    if ($source === null || $served === null) {
        return $cache[$kind] = null;
    }
    $identity = [];
    foreach ($source as $item) {
        $id = $item['id'] ?? null;
        if (!is_int($id) || $id < 1 || isset($identity[$id]) || !is_string($item['slug'] ?? null) ||
            !preg_match('/^(?:[a-z0-9-]|%[0-9a-f]{2})+$/iD', $item['slug'])) {
            return $cache[$kind] = null;
        }
        if ($kind === 'blog' && (!is_string($item['date'] ?? null) ||
            !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $item['date'], $date) ||
            !checkdate((int) $date[2], (int) $date[3], (int) $date[1]))) {
            return $cache[$kind] = null;
        }
        $identity[$id] = $item;
    }
    $seen = [];
    foreach ($served as &$item) {
        $id = $item['id'] ?? null;
        if (!is_int($id) || !isset($identity[$id]) || isset($seen[$id])) {
            return $cache[$kind] = null;
        }
        $seen[$id] = true;
        // La minificación puede cambiar el contenido, pero no las URLs públicas.
        $item['slug'] = $identity[$id]['slug'];
        if ($kind === 'blog') {
            $item['date'] = $identity[$id]['date'];
        }
    }
    unset($item);
    if (count($seen) !== count($identity)) {
        return $cache[$kind] = null;
    }
    return $cache[$kind] = $served;
}

function anatodContentPath($kind, $item, $legacy = false) {
    $prefix = $kind === 'blog' ? '/blog/' . str_replace('-', '/', $item['date']) . '/' : '/evento/';
    return $prefix . ($legacy ? '' : $item['id'] . '_') . $item['slug'] . '/';
}

function anatodFindContent($kind, $path) {
    $path = '/' . trim(rawurldecode((string) $path), '/') . '/';
    $pattern = $kind === 'blog' ? '~^/blog/[0-9]{4}/[0-9]{2}/[0-9]{2}/([1-9][0-9]*)_[^/]+/$~D'
        : '~^/evento/([1-9][0-9]*)_[^/]+/$~D';
    $withId = preg_match($pattern, $path, $matches);
    foreach (anatodContentItems($kind) ?? [] as $item) {
        if (($withId && (string) $item['id'] === $matches[1]) ||
            (!$withId && rawurldecode(anatodContentPath($kind, $item, true)) === $path)) {
            return $item;
        }
    }
    return null;
}

function anatodRedirectContent($kind, $item, $requestPath) {
    global $domain, $siteLanguage;
    $path = anatodContentPath($kind, $item);
    if (rawurldecode($path) === '/' . trim(rawurldecode((string) $requestPath), '/') . '/') {
        return;
    }
    $target = in_array($domain, ['anatod.com', 'anatod.es', 'anatod.com.ar', 'anatod.com.mx', 'anatod.eus'], true)
        ? anatodSeoUrl($domain, $path, $siteLanguage) : anatodPageUrl($path);
    header('Location: ' . $target, true, 301);
    exit;
}

function anatodRewriteContentLinks($html) {
    // Solo enlaces relativos del contenido: preserva imágenes y terceros.
    return preg_replace_callback('~(<a\b[^>]*?\bhref\s*=\s*)(["\'])(/[^"\']*)\2~i', static function ($match) {
        $href = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (substr($href, 0, 2) === '//') {
            return $match[0];
        }
        $parts = parse_url($href);
        if ($parts === false) {
            return $match[0];
        }
        $path = $parts['path'] ?? '/';
        $eventPath = preg_replace('~^/blog/event/~', '/evento/', $path);
        $kind = strpos($eventPath, '/evento/') === 0 ? 'events' : 'blog';
        $item = anatodFindContent($kind, $eventPath);
        if ($item) {
            $href = anatodContentPath($kind, $item) .
                (isset($parts['query']) ? '?' . $parts['query'] : '') .
                (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        }
        return $match[1] . $match[2] . anatodSeoEscape(anatodPageUrl($href)) . $match[2];
    }, (string) $html);
}
