<?php
/**
 * Router for PHP Built-in Server when document root is public/
 *
 * Run: php -S localhost:8000 -t public public/router.php
 * From the project root directory.
 *
 * Lets the server serve real files (css, js, images) and routes the rest to index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Existing file in public/ → let server serve it (return false)
if ($uri !== '/' && $uri !== '' && is_file($file) && strpos(realpath($file), realpath(__DIR__)) === 0) {
    return false;
}

// Everything else → front controller
require __DIR__ . '/index.php';
