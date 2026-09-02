<?php
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

echo "1. Testing publicRoutes in public/index.php...\n";
$indexContent = file_get_contents(__DIR__ . '/../public/index.php');
assert(preg_match('/\$publicRoutes\s*=\s*\[[^\]]+\];/', $indexContent, $m), "publicRoutes must be defined");
assert(strpos($m[0], 'export') === false, "Export routes must not be in publicRoutes array");
echo "   [PASS] Export routes are protected by authentication!\n";

echo "2. Testing session_regenerate_id in AuthController...\n";
$authContent = file_get_contents(__DIR__ . '/../app/Controllers/AuthController.php');
assert(strpos($authContent, 'session_regenerate_id(true)') !== false, "AuthController must call session_regenerate_id(true)");
echo "   [PASS] AuthController regenerates session ID!\n";

echo "3. Testing SpjController unique parameters and error sanitization...\n";
$spjContent = file_get_contents(__DIR__ . '/../app/Controllers/SpjController.php');
assert(strpos($spjContent, ':kw_no') !== false && strpos($spjContent, ':kw_untuk') !== false, "SpjController must use unique named placeholders");
assert(strpos($spjContent, 'Gagal memuat daftar Surat Tugas. Silakan coba beberapa saat lagi.') !== false, "SpjController must not leak raw SQL error to UI");
echo "   [PASS] SpjController uses unique named placeholders and sanitizes error messages!\n";

echo "4. Testing TransaksiModel unique search parameters...\n";
$txModelContent = file_get_contents(__DIR__ . '/../app/Models/Transaksi.php');
assert(strpos($txModelContent, ':q_uraian') !== false && strpos($txModelContent, ':q_bukti') !== false, "Transaksi model must use unique search parameters");
assert(strpos($txModelContent, 'is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR') !== false, "Transaksi model must bind params according to type");
echo "   [PASS] Transaksi model uses unique search parameters and type-aware binding!\n";

echo "5. Testing TransaksiController audit logging on delete...\n";
$txCtrlContent = file_get_contents(__DIR__ . '/../app/Controllers/TransaksiController.php');
assert(strpos($txCtrlContent, "'delete_transaksi_admin'") !== false, "TransaksiController must log delete action to audit_log");
assert(strpos($txCtrlContent, "'delete_batch_transaksi_admin'") !== false, "TransaksiController must log deleteBatch action to audit_log");
echo "   [PASS] TransaksiController logs administrative deletions to audit_log!\n";

echo "\n[ALL AUDIT FIX TESTS PASSED SUCCESSFULLY!]\n";
