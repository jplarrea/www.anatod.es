<?php
require_once __DIR__ . '/languages/catalog.php';
require_once __DIR__ . '/content.php';
// Funciones compartidas por páginas, sitemap y enlaces. No ejecutan plantillas.
function anatodSeoEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function anatodSeoPages() {
    static $pages;
    if ($pages === null) {
        $data = json_decode(file_get_contents(__DIR__ . '/assets/config/seo.min.json'), true);
        $pages = is_array($data) ? $data : [];
    }
    return $pages;
}

function anatodSeoPath($path) {
    $path = '/' . ltrim((string) $path, '/');
    foreach (anatodSeoPages() as $canonical => $page) {
        if ($path === '/' . $page['script'] || rtrim($path, '/') === rtrim($canonical, '/')) {
            return $canonical;
        }
    }
    if (preg_match('~^/(?:evento/(?:[1-9][0-9]*_)?[a-z0-9-]+|blog/[0-9]{4}/[0-9]{2}/[0-9]{2}/[^/]+)/?$~D', $path)) {
        return rtrim($path, '/') . '/';
    }
    return $path;
}

function anatodPageUrl($href, $language = null, $host = null) {
    global $domain, $siteLanguage;
    // Fragmentos y destinos externos conservan su identidad.
    if (!is_string($href) || substr($href, 0, 1) !== '/' || substr($href, 0, 2) === '//') {
        return $href;
    }
    $parts = parse_url($href);
    if ($parts === false) {
        return '/';
    }
    $host = $host ?? ($domain ?? 'anatod.com');
    $language = $language ?? ($siteLanguage ?? 'es');
    parse_str($parts['query'] ?? '', $parameters);
    unset($parameters['lang']);
    if ($host === 'anatod.com' && $language !== 'es') {
        $parameters['lang'] = $language;
    }
    $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    return anatodSeoPath($parts['path'] ?? '/') . ($query !== '' ? '?' . $query : '') .
        (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function anatodSeoUrl($host, $path, $language = 'es') {
    return 'https://www.' . $host . anatodPageUrl($path, $language, $host);
}

function anatodLanguageUrl($language) {
    global $languageDefinitions;
    $host = $languageDefinitions[$language]['domain'];
    $path = anatodSeoPath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($path === '/blog/' && $page !== false && $page > 1) {
        $path .= '?pagina=' . $page;
    }
    return anatodSeoUrl($host, $path, $language);
}

function anatodSeoCatalog($language) {
    return anatodLoadCatalog($language);
}

function anatodSeoItemTranslated($item, $fields, $language) {
    if ($language === 'es') {
        return true;
    }
    $catalog = anatodSeoCatalog($language);
    foreach ($fields as $field) {
        $text = $item[$field] ?? '';
        $key = anatodContentKey(isset($item['content_html']) ? 'blog' : 'events', $item, $field);
        $translation = $catalog[$key] ?? ($catalog[$text] ?? null);
        if (is_string($text) && trim($text) !== '' &&
            (!is_string($translation) || trim($translation) === '')) {
            return false;
        }
    }
    return true;
}

function anatodSeoTranslated($path, $language) {
    if ($language === 'es') {
        return true;
    }
    static $items;
    if ($items === null) {
        $items = [];
        foreach (['blog', 'events'] as $kind) {
            $data = anatodContentItems($kind);
            foreach (is_array($data) ? $data : [] as $item) {
                $post = isset($item['content_html']);
                $url = anatodContentPath($kind, $item);
                $items[rawurldecode($url)] = [$item, $post ? ['title', 'excerpt', 'content_html'] : ['title', 'detail']];
            }
        }
    }
    $key = rawurldecode(anatodSeoPath($path));
    if (isset($items[$key])) {
        return anatodSeoItemTranslated($items[$key][0], $items[$key][1], $language);
    }
    return true;
}

function anatodSeoAlternates($path) {
    $alternates = [];
    foreach (['es' => 'anatod.com', 'es-ES' => 'anatod.es', 'es-AR' => 'anatod.com.ar',
        'es-MX' => 'anatod.com.mx', 'en' => 'anatod.com', 'eu' => 'anatod.eus'] as $locale => $host) {
        $language = explode('-', $locale)[0];
        if (anatodSeoTranslated(parse_url($path, PHP_URL_PATH), $language)) {
            $alternates[$locale] = anatodSeoUrl($host, $path, $language);
        }
    }
    $alternates['x-default'] = anatodSeoUrl('anatod.com', $path);
    return $alternates;
}

function anatodRegionalConfig() {
    global $domain;
    static $config;
    if ($config === null) {
        $data = json_decode(file_get_contents(__DIR__ . '/assets/config/site.min.json'), true);
        $host = isset($data['domains'][$domain ?? '']) ? $domain : ($data['defaultDomain'] ?? 'anatod.com');
        $config = array_replace_recursive($data['defaults'] ?? [], $data['domains'][$host] ?? []);
        $config['pricing'] = $data['pricing'] ?? [];
    }
    return $config;
}

function anatodPrice($key) {
    global $siteLocale;
    $config = anatodRegionalConfig();
    $currency = $config['currency'] ?? 'USD';
    $amount = $config['pricing']['rates'][$key][$currency] ?? null;
    if (!is_numeric($amount)) {
        return _l('Cotización a medida');
    }
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter($siteLocale, NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        $formatted = $formatter->formatCurrency((float) $amount, $currency);
        if ($formatted !== false) {
            return $formatted;
        }
    }
    // El fallback sigue mostrando un importe y una moneda inequívocos sin intl.
    $spanish = substr($siteLocale ?? 'es', 0, 2) === 'es';
    return number_format((float) $amount, 0, $spanish ? ',' : '.', $spanish ? '.' : ',') . ' ' . $currency;
}

function anatodRenderSeo() {
    global $domain, $siteLanguage, $siteLocale, $post, $evento, $title, $description,
        $titulo, $detalle, $isListing, $currentPage, $blogUnavailable;
    if (!in_array($domain, ['anatod.com', 'anatod.es', 'anatod.com.ar', 'anatod.com.mx', 'anatod.eus'], true)) {
        return;
    }
    $path = anatodSeoPath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $pages = anatodSeoPages();
    $metadata = $pages[$path] ?? [];
    $pageTitle = _l($metadata['title'] ?? 'anatod · Software de gestión para ISP');
    $pageDescription = _l($metadata['description'] ?? 'Software de gestión para ISP.');
    if ($path === '/privacy-policy/' && !in_array($domain, ['anatod.es', 'anatod.eus'], true)) {
        $pageTitle = _l('Privacy Policy');
        $pageDescription = _l('Read the anatod privacy policy.');
    }
    $article = is_array($post ?? null) ? $post : null;
    $event = is_array($evento ?? null) ? $evento : null;
    $type = 'website';
    $image = 'https://www.' . $domain . '/assets/img/homeSistema.jpg';
    if ($path === '/blog/' || $article || preg_match('~^/blog/[0-9]{4}/~', $path)) {
        $pageTitle = ($title ?? _l('Novedades')) . ' · anatod';
        $pageDescription = $description ?? _l('Todas las novedades de anatod.');
        if (($isListing ?? false) && ($currentPage ?? 1) > 1) {
            $path .= '?pagina=' . $currentPage;
        }
        if ($article) {
            $type = 'article';
            $image = $article['image']['url'] ?? $image;
        }
    } elseif ($event || strpos($path, '/evento/') === 0) {
        $pageTitle = ($titulo ?? _l('Evento no encontrado')) . ' · anatod';
        $pageDescription = $detalle ?? _l('No encontramos el evento solicitado.');
        $image = $event['photo'] ?? $image;
    }
    if (substr($image, 0, 1) === '/') {
        $image = 'https://www.' . $domain . $image;
    }
    $canonical = anatodSeoUrl($domain, $path, $siteLanguage);
    $status = http_response_code();
    $indexable = (!$status || $status < 400) && !($blogUnavailable ?? false) &&
        anatodSeoTranslated(parse_url($path, PHP_URL_PATH), $siteLanguage);
    $escape = 'anatodSeoEscape';
    echo '<link rel="canonical" href="' . $escape($canonical) . '">' . "\n";
    if (!$indexable) {
        echo '<meta name="robots" content="noindex,follow">' . "\n";
    } else {
        foreach (anatodSeoAlternates($path) as $language => $url) {
            echo '<link rel="alternate" hreflang="' . $escape($language) . '" href="' . $escape($url) . '">' . "\n";
        }
    }
    foreach (['og:type' => $type, 'og:locale' => str_replace('-', '_', $siteLocale),
        'og:site_name' => 'anatod', 'og:title' => $pageTitle, 'og:description' => $pageDescription,
        'og:url' => $canonical, 'og:image' => $image] as $key => $value) {
        echo '<meta property="' . $key . '" content="' . $escape($value) . '">' . "\n";
    }
    foreach (['twitter:card' => 'summary_large_image', 'twitter:title' => $pageTitle,
        'twitter:description' => $pageDescription, 'twitter:image' => $image] as $key => $value) {
        echo '<meta name="' . $key . '" content="' . $escape($value) . '">' . "\n";
    }
    if (!$indexable) {
        return;
    }
    $home = anatodSeoUrl($domain, '/', $siteLanguage);
    $organization = 'https://www.' . $domain . '/#organization';
    $config = anatodRegionalConfig();
    $graph = [
        ['@type' => 'Organization', '@id' => $organization, 'name' => 'anatod',
            'url' => 'https://www.' . $domain . '/',
            'logo' => 'https://d138vzth4rmzi0.cloudfront.net/logo_anatod_min.png',
            'email' => $config['contact']['label'], 'telephone' => '+' . $config['contact']['number'],
            'sameAs' => ['https://www.facebook.com/anatodsoftware', 'https://x.com/anatodSoftware',
                'https://www.instagram.com/anatodsoftware/',
                'https://www.youtube.com/channel/UC5hoxhPD8SEM9c20fUiIbhQ']],
        ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => 'anatod',
            'inLanguage' => $siteLocale, 'publisher' => ['@id' => $organization]],
        ['@type' => $article ? 'BlogPosting' : 'WebPage', '@id' => $canonical . '#page',
            'url' => $canonical, 'name' => $pageTitle, 'description' => $pageDescription,
            'inLanguage' => $siteLocale, 'isPartOf' => ['@id' => $home . '#website']],
    ];
    if ($article) {
        $graph[2]['headline'] = $article['title'];
        $graph[2]['datePublished'] = $article['date'];
        $graph[2]['image'] = $image;
        $graph[2]['publisher'] = ['@id' => $organization];
        $graph[2]['mainEntityOfPage'] = $canonical;
    }
    if ($path === '/software') {
        $graph[] = ['@type' => 'SoftwareApplication', 'name' => 'ISPkeeper', 'url' => $canonical,
            'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web',
            'description' => $pageDescription, 'publisher' => ['@id' => $organization],
            'offers' => ['@type' => 'Offer', 'url' => $canonical . '#precios',
                'price' => $config['pricing']['rates']['tier5000'][$config['currency']],
                'priceCurrency' => $config['currency'],
                'priceSpecification' => ['@type' => 'UnitPriceSpecification',
                    'price' => $config['pricing']['rates']['tier5000'][$config['currency']],
                    'priceCurrency' => $config['currency'], 'unitCode' => 'MON']]];
    }
    if (parse_url($path, PHP_URL_PATH) !== '/') {
        $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'anatod', 'item' => $home]];
        if (strpos($path, '/ispkeeper/') === 0 || $article || $event) {
            $parent = strpos($path, '/ispkeeper/') === 0 ? '/software' : ($event ? '/events/' : '/blog/');
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2,
                'name' => _l($pages[$parent]['title']), 'item' => anatodSeoUrl($domain, $parent, $siteLanguage)];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $pageTitle, 'item' => $canonical];
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
    }
    echo '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
