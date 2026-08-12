<?php
/**
 * Helper Functions
 */

/**
 * Get base URL for the application
 * 
 * @return string Base URL path
 */
function base_url(string $path = ''): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $scriptDir = dirname($scriptName);
    $scriptDir = str_replace('\\', '/', $scriptDir);

    // Base path: empty when app is at document root (e.g. router.php or index.php in public)
    if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
        $basePath = '';
    } elseif (substr($scriptDir, -7) === '/public') {
        // Entry from public/index.php or public/router.php: base = /public so assets resolve correctly
        $basePath = $scriptDir;
    } else {
        $basePath = $scriptDir;
    }

    $path = ltrim($path, '/');

    if (empty($path)) {
        return $basePath !== '' ? rtrim($basePath, '/') . '/' : '/';
    }

    return ($basePath !== '' ? rtrim($basePath, '/') . '/' : '/') . $path;
}


