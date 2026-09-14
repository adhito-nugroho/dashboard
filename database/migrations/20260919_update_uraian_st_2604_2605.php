<?php

declare(strict_types=1);

/**
 * Migration: Koreksi tanggal pelaksanaan pada uraian transaksi untuk Surat Tugas No. 2604 dan 2605.
 * Format uraian yang diperbaiki:
 * "Pada tanggal 13/08/2026 sd 14/08/2026, sesuai Surat Tugas No.: ..."
 */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../../config/load_env.php';
    require_once __DIR__ . '/../../config/database.php';
    $pdo = \Database::getConnection();
}

$stData = [
    '2604' => [
        'id'              => null,
        'tanggal_mulai'   => '2026-08-13',
        'tanggal_selesai' => '2026-08-14',
    ],
    '2605' => [
        'id'              => null,
        'tanggal_mulai'   => '2026-08-13',
        'tanggal_selesai' => '2026-08-14',
    ]
];

// Coba hubungkan ke db_surat_tugas untuk mengambil data tanggal real jika tersedia
try {
    $dbSTConfig = __DIR__ . '/../../config/database_surat_tugas.php';
    if (file_exists($dbSTConfig)) {
        require_once $dbSTConfig;
        if (class_exists('DatabaseSuratTugas')) {
            $stDb = \DatabaseSuratTugas::getConnection();
            if ($stDb) {
                $stQuery = $stDb->query("
                    SELECT id, nomor_surat, tanggal_mulai, tanggal_selesai, untuk 
                    FROM surat_tugas 
                    WHERE nomor_surat LIKE '%2604%' OR nomor_surat LIKE '%2605%'
                ");
                $rowsST = $stQuery->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rowsST as $row) {
                    $nomor = (string) $row['nomor_surat'];
                    foreach (['2604', '2605'] as $targetNo) {
                        if (strpos($nomor, $targetNo) !== false) {
                            $stData[$targetNo]['id'] = (int) $row['id'];
                            if (!empty($row['tanggal_mulai'])) {
                                $stData[$targetNo]['tanggal_mulai'] = $row['tanggal_mulai'];
                            }
                            if (!empty($row['tanggal_selesai'])) {
                                $stData[$targetNo]['tanggal_selesai'] = $row['tanggal_selesai'];
                            }
                        }
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Gunakan fallback tanggal pelaksanaan yang sudah didefinisikan
}

// Cari transaksi yang berelasi dengan nomor surat tugas 2604 atau 2605
$stmt = $pdo->query("
    SELECT id, nomor_surat_tugas, tanggal_pelaksanaan, surat_tugas_ref_id, uraian
    FROM `transaksi`
    WHERE `uraian` LIKE '%2604%'
       OR `uraian` LIKE '%2605%'
       OR `nomor_surat_tugas` LIKE '%2604%'
       OR `nomor_surat_tugas` LIKE '%2605%'
");

$transaksiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updatedCount = 0;

$updateStmt = $pdo->prepare("
    UPDATE `transaksi` 
    SET `uraian` = :uraian, 
        `tanggal_pelaksanaan` = :tanggal_pelaksanaan,
        `surat_tugas_ref_id` = COALESCE(:surat_tugas_ref_id, `surat_tugas_ref_id`)
    WHERE `id` = :id
");

foreach ($transaksiRows as $row) {
    $id = (int) $row['id'];
    $originalUraian = (string) $row['uraian'];
    
    // Tentukan apakah 2604 atau 2605
    $targetKey = null;
    if (strpos($originalUraian, '2605') !== false || strpos((string)$row['nomor_surat_tugas'], '2605') !== false) {
        $targetKey = '2605';
    } elseif (strpos($originalUraian, '2604') !== false || strpos((string)$row['nomor_surat_tugas'], '2604') !== false) {
        $targetKey = '2604';
    }

    if (!$targetKey || !isset($stData[$targetKey])) {
        continue;
    }

    $info = $stData[$targetKey];
    $tglMulai = $info['tanggal_mulai'];
    $tglSelesai = $info['tanggal_selesai'];

    // Format tanggal Indonesia
    $startFmt = date('d/m/Y', strtotime($tglMulai));
    $endFmt   = !empty($tglSelesai) ? date('d/m/Y', strtotime($tglSelesai)) : $startFmt;

    $dateString = ($startFmt !== $endFmt) ? "{$startFmt} sd {$endFmt}" : $startFmt;

    // Ganti "Pada tanggal [apapun sebelum koma/sesuai]" menjadi "Pada tanggal DD/MM/YYYY sd DD/MM/YYYY"
    $newUraian = $originalUraian;
    if (preg_match('/(Pada\s+tanggal\s+)(?:[^\,]+)(,\s*sesuai\s+Surat\s+Tugas)/i', $originalUraian)) {
        $newUraian = preg_replace(
            '/(Pada\s+tanggal\s+)(?:[^\,]+)(,\s*sesuai\s+Surat\s+Tugas)/i',
            '${1}' . $dateString . '${2}',
            $originalUraian
        );
    } else {
        // Fallback jika format sedikit berbeda
        $newUraian = preg_replace(
            '/(Pada\s+tanggal\s+)([0-9\/\-\s]+(?:sd\s+[0-9\/\-]+)?)/i',
            '${1}' . $dateString,
            $originalUraian
        );
    }

    $updateStmt->execute([
        ':uraian'              => $newUraian,
        ':tanggal_pelaksanaan' => $tglMulai,
        ':surat_tugas_ref_id'  => $info['id'] ?: null,
        ':id'                  => $id
    ]);

    $updatedCount++;
}

if (function_exists('migration_log')) {
    migration_log("Koreksi uraian transaksi Surat Tugas 2604 & 2605: {$updatedCount} baris diperbarui.", 'info');
} else {
    echo "Koreksi uraian transaksi Surat Tugas 2604 & 2605: {$updatedCount} baris diperbarui.\n";
}
