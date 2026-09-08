<?php
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
require_once __DIR__ . '/../database/migrations/20260915_add_status_kas_bank.php';

echo "MIGRATION_STATUS_OK\n";
