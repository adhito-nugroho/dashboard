<?php

declare(strict_types=1);

/**
 * Diagnosa "SPD tidak dapat dibuat: transaksi ini belum tertaut ke Surat
 * Tugas + NIP pegawai yang valid" — READ-ONLY, hanya SELECT.
 *
 * Jalankan di server (CLI):
 *   php tools/cek_spd.php DEDI 3127
 *   (arg1 = kata kunci nama/uraian, arg2 = nomor Surat Tugas)
 *
 * Hapus file ini setelah diagnosa selesai.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya untuk CLI.');
}

require __DIR__ . '/../config/load_env.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/database_surat_tugas.php';

$keyword = trim((string) ($argv[1] ?? 'DEDI'));
$nomorSt = trim((string) ($argv[2] ?? '3127'));

$line = str_repeat('=', 78);
echo $line . PHP_EOL;
echo "DIAGNOSA SPD — kata kunci: '{$keyword}' | nomor ST: '{$nomorSt}'" . PHP_EOL;
echo $line . PHP_EOL;

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    echo 'GAGAL konek db_anggaran: ' . $e->getMessage() . PHP_EOL;
    echo 'Pastikan config/.env ada dan berisi DB_HOST/DB_NAME/DB_USER/DB_PASS.' . PHP_EOL;
    exit(1);
}

$st = DatabaseSuratTugas::getConnection();
if ($st === null) {
    echo 'PERINGATAN: db_surat_tugas tidak bisa diakses; cek ST/NIP akan dilewati.' . PHP_EOL;
}

$kw = '%' . $keyword . '%';
$q = $db->prepare(
    "SELECT id, tanggal, nomor_bukti, jenis_transaksi, status, input_by,
            surat_tugas_ref_id, nomor_surat_tugas, pegawai_nip, nama_penerima, uraian
     FROM transaksi
     WHERE jenis_transaksi = 'perjalanan_dinas'
       AND (nama_penerima LIKE :kw1 OR uraian LIKE :kw2 OR nomor_surat_tugas LIKE :kw3)
     ORDER BY id DESC
     LIMIT 20"
);
$q->execute([':kw1' => $kw, ':kw2' => $kw, ':kw3' => $kw]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "Tidak ada transaksi perjalanan_dinas yang cocok dengan '{$keyword}'." . PHP_EOL;
    echo "Coba kata kunci lain, mis. potongan nama atau nomor surat tugas." . PHP_EOL;
}

/** Cari id ST: ref_id dulu, lalu nomor (persis, lalu LIKE) — sama seperti SpdExportService. */
$resolveSt = function (array $trx) use ($st): array {
    $stId = (int) ($trx['surat_tugas_ref_id'] ?? 0);
    if ($stId > 0) {
        return [$stId, 'surat_tugas_ref_id'];
    }
    $nomor = trim((string) ($trx['nomor_surat_tugas'] ?? ''));
    if ($nomor === '' || $st === null) {
        return [0, 'kosong'];
    }
    $s = $st->prepare('SELECT id FROM surat_tugas WHERE nomor_surat = ? LIMIT 1');
    $s->execute([$nomor]);
    $id = $s->fetchColumn();
    if ($id !== false) {
        return [(int) $id, 'nomor persis'];
    }
    $s = $st->prepare('SELECT id FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1');
    $s->execute(['%' . $nomor . '%']);
    $id = $s->fetchColumn();
    return $id !== false ? [(int) $id, 'nomor LIKE'] : [0, 'tidak ditemukan'];
};

/** Cek kecocokan pegawai persis seperti SuratTugasRepository::findPegawai(). */
$findPegawai = function (int $stId, string $nip) use ($st): ?array {
    if ($st === null || $stId <= 0 || $nip === '') {
        return null;
    }
    $s = $st->prepare(
        "SELECT pt.nip AS pt_nip, COALESCE(p.nip, pt.nip) AS nip_efektif,
                COALESCE(p.nama, pt.nip, 'Pegawai') AS nama
         FROM pegawai_tugas pt
         LEFT JOIN pegawai p ON (p.nip = pt.nip OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', ''))
         WHERE pt.id_surat_tugas = ?
           AND (pt.nip = ? OR REPLACE(pt.nip, ' ', '') = REPLACE(?, ' ', ''))
         LIMIT 1"
    );
    $s->execute([$stId, $nip, $nip]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
};

$daftarPegawai = function (int $stId) use ($st): array {
    if ($st === null || $stId <= 0) {
        return [];
    }
    $s = $st->prepare(
        "SELECT pt.nip AS pt_nip, COALESCE(p.nip, pt.nip) AS nip_efektif,
                COALESCE(p.nama, pt.nip, 'Pegawai') AS nama, p.jabatan
         FROM pegawai_tugas pt
         LEFT JOIN pegawai p ON (p.nip = pt.nip OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', ''))
         WHERE pt.id_surat_tugas = ?
         ORDER BY pt.urutan ASC, p.nama ASC"
    );
    $s->execute([$stId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
};

foreach ($rows as $trx) {
    $id = (int) $trx['id'];
    $nip = trim((string) ($trx['pegawai_nip'] ?? ''));

    echo PHP_EOL . str_repeat('-', 78) . PHP_EOL;
    echo "Transaksi #{$id} | tanggal {$trx['tanggal']} | bukti {$trx['nomor_bukti']} | status {$trx['status']}" . PHP_EOL;
    echo "  nama_penerima   : " . ($trx['nama_penerima'] ?? '') . PHP_EOL;
    echo "  surat_tugas_ref_id : " . var_export($trx['surat_tugas_ref_id'], true) . PHP_EOL;
    echo "  nomor_surat_tugas  : '" . (string) $trx['nomor_surat_tugas'] . "'" . PHP_EOL;
    echo "  pegawai_nip        : " . ($nip === '' ? 'NULL/KOSONG' : "'{$nip}'") . PHP_EOL;

    [$stId, $cara] = $resolveSt($trx);
    echo "  ST ter-resolve     : " . ($stId > 0 ? "#{$stId} (via {$cara})" : "TIDAK ({$cara})") . PHP_EOL;

    if ($stId > 0 && $st !== null) {
        $s = $st->prepare('SELECT nomor_surat, tanggal_surat, tanggal_mulai, tanggal_selesai FROM surat_tugas WHERE id = ? LIMIT 1');
        $s->execute([$stId]);
        $stRow = $s->fetch(PDO::FETCH_ASSOC);
        if ($stRow) {
            echo "  ST di server       : nomor {$stRow['nomor_surat']} | surat {$stRow['tanggal_surat']} | laks {$stRow['tanggal_mulai']} s.d. {$stRow['tanggal_selesai']}" . PHP_EOL;
        } else {
            echo "  ST di server       : tidak ditemukan (ref_id menunjuk ST yang sudah dihapus?)" . PHP_EOL;
        }
    }

    // Evaluasi persis seperti buildDataFromTransaksi().
    echo '  VERDICT            : ';
    if ($stId <= 0) {
        echo 'GAGAL — ST tidak tertaut (ref_id kosong & nomor tidak cocok).' . PHP_EOL;
    } elseif ($nip === '' || $nip === '-') {
        echo 'GAGAL — NIP pegawai kosong/NULL. Ini penyebab pesan error SPD.' . PHP_EOL;
    } else {
        $peg = $findPegawai($stId, $nip);
        if ($peg === null) {
            echo "GAGAL — NIP '{$nip}' tidak ada di pegawai_tugas ST #{$stId} (format berbeda / pegawai sudah diubah)." . PHP_EOL;
        } else {
            echo "OK — pegawai '{$peg['nama']}' (NIP {$peg['nip_efektif']}) cocok. SPD seharusnya bisa." . PHP_EOL;
        }
    }

    if ($stId > 0) {
        $pegawai = $daftarPegawai($stId);
        echo '  Pegawai ST         :' . PHP_EOL;
        foreach ($pegawai as $p) {
            $pnip = trim((string) ($p['pt_nip'] ?? ''));
            $label = $pnip === '' ? 'NON-NIP (pt.nip NULL)' : $pnip;
            echo "    - " . ($p['nama'] ?? '-') . " | pt.nip: {$label} | nip efektif: " . (string) ($p['nip_efektif'] ?? '') . PHP_EOL;
        }
    }
}

if ($st !== null && $nomorSt !== '') {
    echo PHP_EOL . str_repeat('-', 78) . PHP_EOL;
    echo "SURAT TUGAS di db_surat_tugas yang nomornya mengandung '{$nomorSt}':" . PHP_EOL;
    $s = $st->prepare('SELECT id, nomor_surat, tanggal_surat, tanggal_mulai, tanggal_selesai FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 10');
    $s->execute(['%' . $nomorSt . '%']);
    $sts = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($sts === []) {
        echo "  (tidak ada)" . PHP_EOL;
    }
    foreach ($sts as $srow) {
        $sid = (int) $srow['id'];
        echo "  #{$sid} | {$srow['nomor_surat']} | surat {$srow['tanggal_surat']} | laks {$srow['tanggal_mulai']} s.d. {$srow['tanggal_selesai']}" . PHP_EOL;
        foreach ($daftarPegawai($sid) as $p) {
            $pnip = trim((string) ($p['pt_nip'] ?? ''));
            echo '      - ' . ($p['nama'] ?? '-') . ' | pt.nip: ' . ($pnip === '' ? 'NON-NIP (NULL)' : $pnip) . PHP_EOL;
        }
    }
}

echo PHP_EOL . $line . PHP_EOL;
echo 'Selesai. Hapus tools/cek_spd.php setelah diagnosa.' . PHP_EOL;
