<?php

$transaksis = [
    // Shared ST 2696 (2 items)
    ['id' => 101, 'nomor_surat_tugas' => '2696'],
    ['id' => 102, 'nomor_surat_tugas' => '2696'],
    // Non-ST item
    ['id' => 103, 'nomor_surat_tugas' => ''],
    // Single ST item (only 1 item with ST 3001)
    ['id' => 104, 'nomor_surat_tugas' => '3001'],
];

// Logic under test:
$stCounts = [];
foreach ($transaksis as $t) {
    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
    if ($stNum !== '') {
        $stCounts[$stNum] = ($stCounts[$stNum] ?? 0) + 1;
    }
}

$results = [];
$prevSt = null;
foreach ($transaksis as $t) {
    $noST = trim((string)($t['nomor_surat_tugas'] ?? ''));
    $isSharedSt = ($noST !== '' && ($stCounts[$noST] ?? 0) >= 2);
    $isNewStGroup = ($isSharedSt && $noST !== $prevSt);
    $prevSt = $noST;
    $results[] = [
        'id' => $t['id'],
        'isSharedSt' => $isSharedSt,
        'isNewStGroup' => $isNewStGroup,
    ];
}

// Assertions:
assert(count($results) === 4, "Semua 4 transaksi harus tetap ada sebagai baris mandiri (flat)");

assert($results[0]['isSharedSt'] === true, "Item 101 harus ditandai shared ST");
assert($results[0]['isNewStGroup'] === true, "Item 101 harus menjadi awal grup ST baru (separator atas)");

assert($results[1]['isSharedSt'] === true, "Item 102 harus ditandai shared ST");
assert($results[1]['isNewStGroup'] === false, "Item 102 bukan awal grup baru");

assert($results[2]['isSharedSt'] === false, "Item 103 (tanpa ST) tidak boleh ditandai shared ST");
assert($results[2]['isNewStGroup'] === false, "Item 103 tidak boleh menjadi grup baru");

assert($results[3]['isSharedSt'] === false, "Item 104 (ST tunggal) tidak boleh ditandai shared ST");
assert($results[3]['isNewStGroup'] === false, "Item 104 tidak boleh menjadi grup baru");

echo "[PASS] Unit test visual grouping flat table berhasil!\n";
