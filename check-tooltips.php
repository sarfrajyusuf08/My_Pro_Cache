<?php
if (!function_exists('__')) {
    function __($text) { return $text; }
}
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require __DIR__ . '/includes/Options/Schema.php';
$pages = MyProCache\Options\Schema::pages();
$missing = [];
foreach ($pages as $slug => $page) {
    if (empty($page['sections'])) {
        continue;
    }
    foreach ($page['sections'] as $section) {
        if (empty($section['fields'])) {
            continue;
        }
        foreach ($section['fields'] as $key => $field) {
            if (empty($field['tooltip'])) {
                $missing[] = $slug . '::' . $key;
            }
        }
    }
}
file_put_contents(__DIR__ . '/missing-tooltips.log', implode(PHP_EOL, $missing));
