<?php

declare(strict_types=1);

/**
 * Salin rekening pegawai dari aplikasi TPP (SQLite) ke JSON untuk server dashboard.
 *
 * Jalankan di laptop (tempat sigap_tpp.db ada):
 *   php tools/export_rekening_tpp.php
 *
 * Lalu unggah storage/rekening_pegawai.json ke folder storage/ di server.
 * File JSON tidak di-commit (lihat storage/.gitignore).
 */

$source = $argv[1] ?? 'D:/laragon/www/tpp/data/sigap_tpp.db';
$output = $argv[2] ?? (__DIR__ . '/../storage/rekening_pegawai.json');

if (!is_file($source)) {
    fwrite(STDERR, "DB TPP tidak ditemukan: {$source}\n");
    fwrite(STDERR, "Jalankan: php tools/export_rekening_tpp.php \"D:\\path\\ke\\sigap_tpp.db\"\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $source);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $rows = $pdo->query("
        SELECT nip, nama, no_rekening, status_aktif, kategori
        FROM pegawai
        WHERE no_rekening IS NOT NULL AND TRIM(no_rekening) <> ''
        ORDER BY nama ASC
    ")->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal membaca DB TPP: {$e->getMessage()}\n");
    exit(1);
}

if ($rows === []) {
    fwrite(STDERR, "Tidak ada pegawai dengan nomor rekening di DB TPP.\n");
    exit(1);
}

$dir = dirname($output);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Gagal membuat folder: {$dir}\n");
    exit(1);
}

$json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($output, $json . "\n") === false) {
    fwrite(STDERR, "Gagal menulis: {$output}\n");
    exit(1);
}

echo "Rekening pegawai: " . count($rows) . " baris -> {$output}\n";
echo "Unggah file tersebut ke storage/rekening_pegawai.json di server.\n";
