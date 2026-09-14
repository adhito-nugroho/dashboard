<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

$db = Database::getConnection();
$model = new \App\Models\Transaksi($db);

$results = $model->getWithFilters(9, 2026);
echo "Jumlah transaksi hasil filter September 2026: " . count($results) . "\n";

$valid = true;
foreach ($results as $r) {
    $effDate = !empty($r['tanggal_lunas_dibayar']) 
        ? $r['tanggal_lunas_dibayar'] 
        : (!empty($r['diverifikasi_at']) ? substr($r['diverifikasi_at'], 0, 10) : $r['tanggal']);
    
    $m = (int) date('n', strtotime($effDate));
    $y = (int) date('Y', strtotime($effDate));
    
    if ($m !== 9 || $y !== 2026) {
        echo "FAIL: Transaksi ID {$r['id']} memiliki tanggal efektif {$effDate} (Bulan $m, Tahun $y)\n";
        $valid = false;
        break;
    }
}

if ($valid) {
    echo "✓ SEMUA transaksi yang difilter valid sesuai bulan dibayar/diverifikasi (September 2026)!\n";
} else {
    exit(1);
}
