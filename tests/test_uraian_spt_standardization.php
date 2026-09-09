<?php
/**
 * Test standardisasi uraian SPT perjalanan dinas
 */
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../database/migrations/20260916_format_uraian_spt_lengkap.php';

$testCases = [
    [
        'input' => 'Perjalanan Dinas dalam rangka Menghadiri Rapat Koordinasi Pelaporan Aksi Pembangunan Rendah Karbon Provinsi Jawa Timur Tahun 2026. Pada tanggal 20/08/2026, sesuai Surat Tugas No.: 2636 tanggal 19/08/2026. Sub Kegiatan Penyelenggaraan Rapat Koordinasi dan Konsultasi SKPD An. ENDANG HANDAYANI, S.P., M.Si.',
        'expected' => 'Perjalanan Dinas dalam rangka Menghadiri Rapat Koordinasi Pelaporan Aksi Pembangunan Rendah Karbon Provinsi Jawa Timur Tahun 2026. Pada tanggal 20/08/2026, sesuai Surat Tugas No.: 800.1.11.1/2636/123.6.6/2026 tanggal 19/08/2026. Sub Kegiatan Penyelenggaraan Rapat Koordinasi dan Konsultasi SKPD An. ENDANG HANDAYANI, S.P., M.Si.'
    ],
    [
        'input' => 'Perjalanan Dinas dalam rangka Koordinasi. Pada tanggal 01/09/2026, sesuai Surat Tugas No.: 800.1.11.1/559/123.6.6/2025 tanggal 01/09/2026. Sub Kegiatan Penyelenggaraan Rapat An. ADHIT',
        'expected' => 'Perjalanan Dinas dalam rangka Koordinasi. Pada tanggal 01/09/2026, sesuai Surat Tugas No.: 800.1.11.1/559/123.6.6/2025 tanggal 01/09/2026. Sub Kegiatan Penyelenggaraan Rapat An. ADHIT'
    ]
];

$passed = true;
foreach ($testCases as $i => $tc) {
    $out = migration_fix_uraian_spt($tc['input']);
    if ($out !== $tc['expected']) {
        echo "[FAIL] Test case #$i failed.\nExpected: {$tc['expected']}\nGot: {$out}\n";
        $passed = false;
    } else {
        echo "[PASS] Test case #$i passed.\n";
    }
}

if ($passed) {
    echo "\nAll SPT standardisation tests passed!\n";
    exit(0);
} else {
    exit(1);
}
