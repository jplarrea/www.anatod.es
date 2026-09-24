<?php
$domain = strtolower($_SERVER['HTTP_HOST'] ?? '');

// Quita www. si existe
$domain = preg_replace('/^www\./', '', $domain);

switch ($domain) {
    case 'anatod.com.mx':
        $gtag = 'G-049VV4DTE0';
        break;
    case 'anatod.com.ar':
        $gtag = 'G-BT0GCMJDP4';
        break;
    case 'anatod.es':
        $gtag = "G-M6P5R8GJ5B";
        break;
    default:
        $gtag = 'G-2PLZ9D6G3D';
        break;
}