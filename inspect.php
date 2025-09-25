<?php
if (!function_exists('__')) {
    function __($text) { return $text; }
}
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require __DIR__ . '/includes/Options/Schema.php';
$pages = MyProCache\Options\Schema::pages();
print_r($pages['optimize']['sections']['minify']['fields']['min_html']);
