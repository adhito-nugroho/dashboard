<?php

declare(strict_types=1);

/**
 * Migration: Standardisasi format nomor SPT pada uraian transaksi perjalanan dinas.
 * Format standar: 800.1.11.1/{nomor_spt}/123.6.6/{tahun}
 */

if (!function_exists('migration_fix_uraian_spt')) {
    function migration_fix_uraian_spt(string $uraian, ?string $tglST = null, ?string $tglPelaksanaan = null, ?string $tglTransaksi = null): string
    {
        // Jika sudah memiliki prefix standar 800.1.11.1, tidak perlu diubah
        if (strpos($uraian, '800.1.11.1') !== false) {
            return $uraian;
        }

        // Pola 1: "Surat (Perintah )?Tugas No.: <angka_saja> ... tanggal DD/MM/YYYY"
        $pattern1 = '/(Surat\s+(?:Perintah\s+)?Tugas\s+No\.?:\s*)([0-9]+)(?!\.[0-9])(.*?\btanggal\s+(\d{1,2}[\/\-]\d{1,2}[\/\-](\d{4})))/i';
        $result = preg_replace_callback(
            $pattern1,
            function($matches) use ($tglST, $tglPelaksanaan, $tglTransaksi) {
                $prefixLabel = $matches[1];
                $nomorRaw = trim($matches[2]);
                $rest = $matches[3];
                $tahun = $matches[5];

                if (!$tahun && $tglST) $tahun = date('Y', strtotime($tglST));
                if (!$tahun && $tglPelaksanaan) $tahun = date('Y', strtotime($tglPelaksanaan));
                if (!$tahun && $tglTransaksi) $tahun = date('Y', strtotime($tglTransaksi));
                if (!$tahun) $tahun = date('Y');

                $nomorLengkap = "800.1.11.1/{$nomorRaw}/123.6.6/{$tahun}";
                return $prefixLabel . $nomorLengkap . $rest;
            },
            $uraian
        );

        // Pola 2: "Surat (Perintah )?Tugas No.: <angka_saja>" tanpa tanggal langsung di belakangnya
        if ($result === $uraian) {
            $pattern2 = '/(Surat\s+(?:Perintah\s+)?Tugas\s+No\.?:\s*)([0-9]+)(?!\.[0-9])(\b|\.)/i';
            $result = preg_replace_callback(
                $pattern2,
                function($matches) use ($tglST, $tglPelaksanaan, $tglTransaksi) {
                    $prefixLabel = $matches[1];
                    $nomorRaw = trim($matches[2]);
                    $suffix = $matches[3];

                    $tahun = '';
                    if ($tglST) $tahun = date('Y', strtotime($tglST));
                    if (!$tahun && $tglPelaksanaan) $tahun = date('Y', strtotime($tglPelaksanaan));
                    if (!$tahun && $tglTransaksi) $tahun = date('Y', strtotime($tglTransaksi));
                    if (!$tahun) $tahun = date('Y');

                    $nomorLengkap = "800.1.11.1/{$nomorRaw}/123.6.6/{$tahun}";
                    return $prefixLabel . $nomorLengkap . $suffix;
                },
                $uraian
            );
        }

        return $result;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

// Ambil semua transaksi yang berpotensi memiliki format nomor SPT pendek
$stmt = $pdo->query("
    SELECT id, tanggal, jenis_transaksi, nomor_surat_tugas, tanggal_surat_tugas, tanggal_pelaksanaan, uraian
    FROM `transaksi`
    WHERE `uraian` IS NOT NULL AND `uraian` != ''
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$updateStmt = $pdo->prepare("UPDATE `transaksi` SET `uraian` = :uraian WHERE `id` = :id");

$updatedCount = 0;
foreach ($rows as $row) {
    $original = (string) $row['uraian'];
    $fixed = migration_fix_uraian_spt(
        $original,
        $row['tanggal_surat_tugas'] ?? null,
        $row['tanggal_pelaksanaan'] ?? null,
        $row['tanggal'] ?? null
    );

    if ($fixed !== $original) {
        $updateStmt->execute([
            ':uraian' => $fixed,
            ':id'     => $row['id']
        ]);
        $updatedCount++;
    }
}

if (function_exists('migration_log')) {
    migration_log("Standardized {$updatedCount} transaksi uraian to full SPT format.", 'info');
}
