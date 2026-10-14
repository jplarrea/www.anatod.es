<?php
function anatodArticleKey($post, $field) {
    return anatodContentKey('blog', $post, $field);
}

function anatodContentKey($kind, $post, $field) {
    if (isset($post['id']) && is_int($post['id']) && $post['id'] > 0) {
        return $kind . '.' . $post['id'] . '.' . $field;
    }
    // Compatibilidad con feeds anteriores mientras se actualizan los JSON.
    return $field === 'image_alt' ? ($post['image']['alt'] ?? '') : ($post[$field] ?? '');
}

// El catálogo fuente conserva las claves y el HTML exactos. Algunos procesos
// de minificación alteran espacios dentro de las cadenas del JSON generado.
function anatodLoadCatalog($language) {
    static $catalogs = [];
    if (isset($catalogs[$language])) {
        return $catalogs[$language];
    }
    // El idioma solo puede provenir del registro de idiomas del sitio.
    if (!is_string($language) || !preg_match('/^[a-z]{2,3}$/D', $language)) {
        return [];
    }
    $catalog = [];
    foreach (['.min.json', '.json'] as $suffix) {
        $path = __DIR__ . '/' . $language . $suffix;
        $data = is_readable($path) ? json_decode(file_get_contents($path), true) : [];
        if (!is_array($data)) {
            continue;
        }
        foreach ($data as $text => $translation) {
            if (is_string($translation) && trim($translation) !== '') {
                $catalog[$text] = $translation;
            }
        }
    }
    $catalogs[$language] = $catalog;
    return $catalog;
}
