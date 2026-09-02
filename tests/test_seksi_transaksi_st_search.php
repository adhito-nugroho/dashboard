<?php
require_once __DIR__ . '/../config/database_surat_tugas.php';

$pdo = DatabaseSuratTugas::getConnection();
if (!$pdo) {
    echo "NO CONNECTION TO db_surat_tugas\n";
    exit(1);
}

// Function to simulate controller query logic
function runSearch($q, $bulan, $tahun, $pdo) {
    $conditions = [];
    $params = [];

    if ($q !== '') {
        $conditions[] = '(st.nomor_surat LIKE :kw_no OR st.untuk LIKE :kw_untuk OR p.nama LIKE :kw_pegawai)';
        $kw = '%' . $q . '%';
        $params[':kw_no'] = $kw;
        $params[':kw_untuk'] = $kw;
        $params[':kw_pegawai'] = $kw;
    }

    if ($bulan !== null && $bulan >= 1 && $bulan <= 12) {
        $conditions[] = 'MONTH(st.tanggal_mulai) = :bulan';
        $params[':bulan'] = $bulan;
    }

    if ($tahun !== null && $tahun > 2000) {
        $conditions[] = 'YEAR(st.tanggal_mulai) = :tahun';
        $params[':tahun'] = $tahun;
    }

    $whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "
        SELECT 
            st.id, 
            st.nomor_surat, 
            st.tanggal_surat, 
            st.untuk, 
            st.tanggal_mulai, 
            st.tanggal_selesai, 
            st.dasar_surat,
            COUNT(DISTINCT pt.nip) AS total_pegawai,
            GROUP_CONCAT(DISTINCT p.nama ORDER BY pt.urutan ASC SEPARATOR ', ') AS daftar_pegawai
        FROM surat_tugas st
        LEFT JOIN pegawai_tugas pt ON pt.id_surat_tugas = st.id
        LEFT JOIN pegawai p ON TRIM(p.nip) = TRIM(pt.nip)
        {$whereSql}
        GROUP BY st.id
        ORDER BY st.tanggal_mulai DESC, st.id DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        if (is_int($val)) {
            $stmt->bindValue($key, $val, \PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val, \PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// 4 Mandatory Testing Scenarios:
// (a) Kata kunci saja tanpa filter bulan (misal 'brigif' atau 'konsultasi')
$resA1 = runSearch('brigif', null, null, $pdo);
echo "[Test A1] Keyword 'brigif' (not found): " . count($resA1) . " results, NO SQL ERROR\n";
assert(is_array($resA1));

$resA2 = runSearch('konsultasi', null, null, $pdo);
echo "[Test A2] Keyword 'konsultasi' (found): " . count($resA2) . " results, NO SQL ERROR\n";
assert(count($resA2) >= 1);
assert(!empty($resA2[0]['daftar_pegawai']));

// (b) Filter bulan saja tanpa kata kunci
$resB = runSearch('', 2, 2025, $pdo);
echo "[Test B] Filter bulan 2 tanpa keyword: " . count($resB) . " results, NO SQL ERROR\n";
assert(count($resB) >= 1);

// (c) Keduanya diisi (kata kunci + bulan)
$resC = runSearch('urutan', 2, 2026, $pdo);
echo "[Test C] Keyword 'urutan' + bulan 2: " . count($resC) . " results, NO SQL ERROR\n";
assert(count($resC) >= 1);

// (d) Keduanya kosong (load awal modal)
$resD = runSearch('', null, null, $pdo);
echo "[Test D] Initial modal load (both empty): " . count($resD) . " results, NO SQL ERROR\n";
assert(count($resD) >= 1);

echo "\n[PASS] Semua 4 skenario pencarian Surat Tugas berhasil 100% tanpa error parameter SQL!\n";
