<?php
require_once 'config.php';

if (!function_exists('_l')) {
    function _l($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

require_once 'views/kit-de-marca.min.html';
