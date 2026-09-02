<?php

$transaksis = [
    // Group ST 2696 (2 items: 1 diajukan, 1 diverifikasi)
    [
        'id' => 101,
        'tanggal' => '2026-09-01',
        'nomor_bukti' => '001/BKT',
        'nomor_surat_tugas' => '2696',
        'nama_penerima' => 'Ahmad',
        'nama_sub_kegiatan' => 'Sub Kegiatan A',
        'kode_sub_kegiatan' => '1.01.01',
        'nama_rekening' => 'Belanja Perjadin',
        'kode_rekening' => '5.1.02.04',
        'uraian' => 'Perjalanan dinas koordinasi ke Surabaya',
        'nilai' => 500000,
        'status' => 'diajukan',
        'jenis_transaksi' => 'perjalanan_dinas',
    ],
    [
        'id' => 102,
        'tanggal' => '2026-09-01',
        'nomor_bukti' => '002/BKT',
        'nomor_surat_tugas' => '2696',
        'nama_penerima' => 'Budi',
        'nama_sub_kegiatan' => 'Sub Kegiatan A',
        'kode_sub_kegiatan' => '1.01.01',
        'nama_rekening' => 'Belanja Perjadin',
        'kode_rekening' => '5.1.02.04',
        'uraian' => 'Perjalanan dinas koordinasi ke Surabaya',
        'nilai' => 450000,
        'status' => 'diverifikasi',
        'jenis_transaksi' => 'perjalanan_dinas',
    ],
    // Single non-ST item
    [
        'id' => 103,
        'tanggal' => '2026-09-02',
        'nomor_bukti' => '003/BKT',
        'nomor_surat_tugas' => '',
        'nama_penerima' => 'Percetakan ABC',
        'nama_sub_kegiatan' => 'Sub Kegiatan B',
        'kode_sub_kegiatan' => '1.01.02',
        'nama_rekening' => 'Belanja Cetak Banner',
        'kode_rekening' => '5.1.02.01',
        'uraian' => 'Belanja cetak spanduk sosialisasi',
        'nilai' => 300000,
        'status' => 'diajukan',
        'jenis_transaksi' => 'belanja',
    ],
    // Single ST item (only 1 item with ST 3001, should not group)
    [
        'id' => 104,
        'tanggal' => '2026-09-03',
        'nomor_bukti' => '004/BKT',
        'nomor_surat_tugas' => '3001',
        'nama_penerima' => 'Citra',
        'nama_sub_kegiatan' => 'Sub Kegiatan A',
        'kode_sub_kegiatan' => '1.01.01',
        'nama_rekening' => 'Belanja Perjadin',
        'kode_rekening' => '5.1.02.04',
        'uraian' => 'Perjadin tunggal',
        'nilai' => 600000,
        'status' => 'diverifikasi',
        'jenis_transaksi' => 'perjalanan_dinas',
    ],
];

// Logic under test:
$stCounts = [];
foreach ($transaksis as $t) {
    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
    if ($stNum !== '') {
        $stCounts[$stNum] = ($stCounts[$stNum] ?? 0) + 1;
    }
}

$groupedItems = [];
$processedSt = [];
foreach ($transaksis as $t) {
    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
    if ($stNum !== '' && ($stCounts[$stNum] ?? 0) >= 2) {
        if (!isset($processedSt[$stNum])) {
            $processedSt[$stNum] = true;
            $members = [];
            foreach ($transaksis as $subT) {
                if (trim((string)($subT['nomor_surat_tugas'] ?? '')) === $stNum) {
                    $members[] = $subT;
                }
            }
            $groupedItems[] = [
                'type'    => 'group',
                'st'      => $stNum,
                'members' => $members,
            ];
        }
    } else {
        $groupedItems[] = [
            'type' => 'single',
            'data' => $t,
        ];
    }
}

$resolveGroupStatus = function(array $members): array {
    $hasDitolak = false;
    $hasDiajukan = false;
    foreach ($members as $m) {
        $st = $m['status'] ?? 'diverifikasi';
        if ($st === 'ditolak') $hasDitolak = true;
        if ($st === 'diajukan') $hasDiajukan = true;
    }
    if ($hasDitolak) {
        return ['Ditolak', 'danger'];
    }
    if ($hasDiajukan) {
        return ['Menunggu Verifikasi', 'warning'];
    }
    return ['Diverifikasi', 'success'];
};

// Assertions:
assert(count($groupedItems) === 3, "Harus ada 3 item setelah grouping (1 grup ST 2696, 1 single non-ST, 1 single ST 3001)");

assert($groupedItems[0]['type'] === 'group', "Item pertama harus group");
assert($groupedItems[0]['st'] === '2696', "Grup harus ST 2696");
assert(count($groupedItems[0]['members']) === 2, "Grup 2696 harus berisi 2 anggota");
assert(array_sum(array_column($groupedItems[0]['members'], 'nilai')) === 950000, "Total nilai grup harus 950.000");

$statusGroup = $resolveGroupStatus($groupedItems[0]['members']);
assert($statusGroup[0] === 'Menunggu Verifikasi', "Status grup campuran (diajukan + diverifikasi) harus Menunggu Verifikasi");

assert($groupedItems[1]['type'] === 'single', "Item non-ST harus bertipe single");
assert($groupedItems[1]['data']['id'] === 103, "ID item non-ST harus 103");

assert($groupedItems[2]['type'] === 'single', "Item ST dengan count = 1 harus bertipe single");
assert($groupedItems[2]['data']['id'] === 104, "ID item ST tunggal harus 104");

// Test mixed with ditolak:
$mixedWithDitolak = [
    ['status' => 'diverifikasi'],
    ['status' => 'ditolak'],
    ['status' => 'diajukan'],
];
$statusWithDitolak = $resolveGroupStatus($mixedWithDitolak);
assert($statusWithDitolak[0] === 'Ditolak', "Status grup campuran dengan 1 ditolak harus Ditolak (prioritas 1)");

echo "[PASS] Semua unit test grouping & resolver status campuran berhasil!\n";
