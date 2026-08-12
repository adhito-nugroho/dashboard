<?php
/**
 * Router for PHP Built-in Server (document root = project root)
 *
 * Run: php -S localhost:8000 router.php
 * From the project root directory.
 *
 * Serves static files from public/ and routes the rest to public/index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/public' . $uri;

// Serve existing static files from public/ (PHP built-in server would look in project root, not public)
if ($uri !== '/' && $uri !== '') {
    if (is_file($file) && strpos(realpath($file), realpath(__DIR__ . '/public')) === 0) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg'=> 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff'=>'font/woff',
            'woff2'=>'font/woff2',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        return true;
    }
}

// Route everything else through index.php
require_once __DIR__ . '/public/index.php';

