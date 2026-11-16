<?php

declare(strict_types=1);

/**
 * Migration: pustaka blanko kalibrasi.
 *
 * Setiap upload contoh kuitansi / lembar 2 SPPD disimpan permanen di
 * public/uploads/kalibrasi/pustaka/<jenis>/, satu baris per file. Satu baris
 * per jenis ditandai is_active agar bisa dipakai ulang tanpa upload ulang.
 *
 * File referensi lama (referensi_kuitansi.* / referensi_sppd_lembar2.*) ikut
 * diimpor sekali agar tetap tersedia di pustaka.
 */

if (!isset($pdo) && class_exists('Database')) {
    $pdo = Database::getConnection();
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `kalibrasi_blanko` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `jenis`       ENUM('kuitansi','sppd_lembar2') NOT NULL,
        `nama_file`   VARCHAR(255) NOT NULL COMMENT 'Nama file di public/uploads/kalibrasi/pustaka/<jenis>/',
        `nama_asli`   VARCHAR(255) NOT NULL COMMENT 'Nama file saat diupload',
        `kind`        ENUM('image','pdf') NOT NULL,
        `ukuran`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ukuran file dalam byte',
        `uploaded_by` VARCHAR(100) NULL,
        `is_active`   TINYINT(1) NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_blanko_jenis` (`jenis`, `is_active`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Pustaka blanko kuitansi/SPPD yang pernah diupload'
");

// ── Impor file referensi lama (sekali) ────────────────────────────────────
$root    = dirname(__DIR__, 2);
$srcDir  = $root . '/public/uploads/kalibrasi';
$mapping = [
    'kuitansi'     => 'referensi_kuitansi',
    'sppd_lembar2' => 'referensi_sppd_lembar2',
];

foreach ($mapping as $jenis => $base) {
    $files = glob($srcDir . '/' . $base . '.{jpg,jpeg,png,pdf}', GLOB_BRACE) ?: [];
    if ($files === []) {
        continue;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM kalibrasi_blanko WHERE jenis = ?');
    $check->execute([$jenis]);
    if ((int) $check->fetchColumn() > 0) {
        continue; // sudah pernah diimpor
    }

    $destDir = $srcDir . '/pustaka/' . $jenis;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    // File terbaru jadi blanko aktif.
    usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));

    $insert = $pdo->prepare("
        INSERT INTO kalibrasi_blanko
            (jenis, nama_file, nama_asli, kind, ukuran, uploaded_by, is_active, created_at)
        VALUES
            (:jenis, :nama_file, :nama_asli, :kind, :ukuran, 'impor-otomatis', :aktif, :created_at)
    ");

    $adaAktif = false;
    foreach ($files as $file) {
        $ext  = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        $kind = $ext === 'pdf' ? 'pdf' : 'image';
        $mtime = (int) filemtime($file);

        $namaFile = 'lama_' . date('Ymd_His', $mtime) . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $destDir . '/' . $namaFile;
        if (!@copy($file, $dest)) {
            continue;
        }

        $insert->execute([
            ':jenis'      => $jenis,
            ':nama_file'  => $namaFile,
            ':nama_asli'  => basename($file),
            ':kind'       => $kind,
            ':ukuran'     => (int) filesize($dest),
            ':aktif'      => $adaAktif ? 0 : 1,
            ':created_at' => date('Y-m-d H:i:s', $mtime),
        ]);
        $adaAktif = true;
    }
}
