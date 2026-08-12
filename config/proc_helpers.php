<?php
/**
 * Procedural helpers: database access, flash message, redirect, rendering.
 * Menggunakan Database::getConnection() yang sudah ada untuk mendapatkan PDO.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Get shared PDO instance.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = Database::getConnection();
    }
    return $pdo;
}

/**
 * Run prepared query with params.
 */
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows.
 */
function db_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch single row.
 */
function db_one(string $sql, array $params = []): ?array {
    $row = db_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Fetch single scalar value.
 */
function db_value(string $sql, array $params = [], $default = null) {
    $stmt = db_query($sql, $params);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

/**
 * Flash message helpers.
 */
function flash_set(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function flash_get(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $msg = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return [$msg, $type];
}

/**
 * Redirect helper.
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Render view within layout.
 */
function render(string $viewFile, array $vars = [], string $pageTitle = 'Dashboard', string $activePage = ''): void {
    extract($vars, EXTR_SKIP);
    $viewFilePath = $viewFile; // for clarity
    $pageTitle = $pageTitle;
    $activePage = $activePage;
    include __DIR__ . '/../views/layout.php';
}


