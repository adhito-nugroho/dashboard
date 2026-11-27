<?php

declare(strict_types=1);

/**
 * Test pemisahan dana UP/GU dan TU di Kas & Bank.
 *
 * Regresi yang dijaga:
 * 1. Setoran sisa TU adalah uang KELUAR — dulu terjumlah sebagai penerimaan
 *    sehingga saldo membengkak 2x nilai setoran dan kelebihannya masuk
 *    komposisi "Uang GU".
 * 2. Belanja dari dana TU tidak boleh memicu Ganti Uang (potensi GU).
 * 3. Dana UP/GU tidak boleh melebihi plafon — kelebihannya milik TU.
 * 4. Pengingat setor sisa TU maksimal 1 bulan.
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/KasBank.php';
require_once __DIR__ . '/../app/Models/PengajuanTu.php';

use App\Models\KasBank;
use App\Models\PengajuanTu;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('GAGAL: ' . $msg);
    }
    echo "  [OK] {$msg}\n";
}

echo "=== TEST PEMISAHAN DANA UP/GU & TU ===\n";

$db    = Database::getConnection();
$kas   = new KasBank($db);
$tu    = new PengajuanTu($db);
$tag   = 'TEST-DANA-' . date('His');
$bulan = 12;
$tahun = 2026;

$seksiId = (int) $db->query("SELECT id FROM seksi ORDER BY id LIMIT 1")->fetchColumn();
$rek = $db->query("
    SELECT r.id FROM rekening r
    INNER JOIN sub_kegiatan sk ON sk.id = r.sub_kegiatan_id
    WHERE sk.seksi_id = {$seksiId}
    ORDER BY r.id LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
if (!$rek) {
    echo "SKIP: tidak ada rekening untuk seksi {$seksiId}\n";
    exit(0);
}
$rekeningId = (int) $rek['id'];

$createdKas  = [];
$createdTu   = [];
$createdTrx  = [];

$insertTu = $db->prepare("
    INSERT INTO pengajuan_tu
        (nomor_pengajuan, tanggal_pengajuan, seksi_id, rekening_id, tahun, bulan,
         kebutuhan, keperluan, status, nomor_sp2d, tanggal_cair, nominal_cair,
         batas_spj, nominal_digunakan, nominal_disetor)
    VALUES
        (:nomor, '2026-12-01', :seksi, :rekening, 2026, 12,
         1000000, :keperluan, :status, 'SP2D-TEST', '2026-12-02', 1000000,
         '2027-01-01', :digunakan, :disetor)
");
$insertTuRek = $db->prepare("
    INSERT INTO pengajuan_tu_rekening (pengajuan_tu_id, rekening_id, nominal) VALUES (?, ?, 1000000)
");
$insertTrx = $db->prepare("
    INSERT INTO transaksi
        (tanggal, tanggal_lunas_dibayar, seksi_id, rekening_id, uraian, nilai,
         status, sumber_dana, pengajuan_tu_id)
    VALUES ('2026-12-05', '2026-12-05', :seksi, :rekening, :uraian, :nilai,
            'diverifikasi', 'UP', :tu)
");

try {
    // ── 1. Siklus TU penuh: cair 1jt → belanja 400rb → setor 600rb ────────
    echo "\n1. Siklus TU penuh tidak mengubah saldo\n";

    $saldoAwal = $kas->getSaldoPerTanggal('2026-12-31');
    $danaAwal  = $kas->getRingkasan($bulan, $tahun);

    $insertTu->execute([
        ':nomor' => $tag . '-A', ':seksi' => $seksiId, ':rekening' => $rekeningId,
        ':keperluan' => 'TEST siklus penuh', ':status' => 'cair',
        ':digunakan' => 400000, ':disetor' => 600000,
    ]);
    $tuId = (int) $db->lastInsertId();
    $createdTu[] = $tuId;
    $insertTuRek->execute([$tuId, $rekeningId]);

    // Pencairan TU masuk kas (arah masuk, kantong TU)
    $kasMasukId = $kas->create('2026-12-02', 'tu', 'SP2D-TEST-' . $tag, 'TEST pencairan TU', 1000000.0, 1, 'cair', '2026-12-02', 'masuk', 'TU');
    $createdKas[] = $kasMasukId;

    // Belanja dari dana TU
    $insertTrx->execute([
        ':seksi' => $seksiId, ':rekening' => $rekeningId,
        ':uraian' => 'TEST belanja TU', ':nilai' => 400000, ':tu' => $tuId,
    ]);
    $createdTrx[] = (int) $db->lastInsertId();

    // Setoran sisa TU KELUAR kembali ke Kasda
    $kasSetorId = $kas->create('2026-12-20', 'setoran', 'SETOR-' . $tag, 'TEST setoran sisa TU', 600000.0, 1, 'cair', '2026-12-20', 'keluar', 'TU');
    $createdKas[] = $kasSetorId;
    $db->prepare('UPDATE pengajuan_tu SET kas_bank_setoran_id = ? WHERE id = ?')->execute([$kasSetorId, $tuId]);

    $saldoAkhir = $kas->getSaldoPerTanggal('2026-12-31');
    $danaAkhir  = $kas->getRingkasan($bulan, $tahun);

    printf("  saldo awal=%.0f saldo akhir=%.0f | dana_tu=%.0f dana_gu=%.0f\n",
        $saldoAwal, $saldoAkhir, $danaAkhir['dana_tu_di_kas'], $danaAkhir['dana_gu_di_kas']);

    ok(abs($saldoAkhir - $saldoAwal) < 0.01,
        'saldo kas tidak berubah: +1jt cair −400rb belanja −600rb setor = 0');
    ok(abs((float) $danaAkhir['dana_tu_di_kas']) < 0.01, 'uang TU = 0 (semua sudah dibelanjakan & disetor)');
    ok(abs((float) $danaAkhir['dana_gu_di_kas'] - (float) $danaAwal['dana_gu_di_kas']) < 0.01,
        'uang GU tidak bertambah karena setoran TU');

    // ── 2. Belanja TU tidak memicu GU ────────────────────────────────────
    echo "\n2. Belanja TU bukan potensi GU\n";
    ok(abs((float) $danaAkhir['belanja_siap_gu'] - (float) $danaAwal['belanja_siap_gu']) < 0.01,
        'belanja_siap_gu tidak bertambah oleh belanja dana TU');

    // ── 3. GU menunggu cair hanya kantong UP_GU ──────────────────────────
    echo "\n3. GU menunggu cair hanya menghitung dana UP/GU\n";
    $guMenungguAwal = (float) $danaAwal['gu_menunggu_cair'];

    $kasGuTuId = $kas->create('2026-12-10', 'gu', 'SP2D-GU-TU-' . $tag, 'TEST GU bersumber TU (tidak boleh)', 250000.0, 1, 'menunggu_cair', null, 'masuk', 'TU');
    $createdKas[] = $kasGuTuId;
    $afterTu = $kas->getRingkasan($bulan, $tahun);
    ok(abs((float) $afterTu['gu_menunggu_cair'] - $guMenungguAwal) < 0.01,
        'GU dengan kantong TU diabaikan dari gu_menunggu_cair');

    $semuaPending = (float) $db->query("
        SELECT COALESCE(SUM(nominal), 0) FROM kas_bank WHERE status = 'menunggu_cair'
    ")->fetchColumn();
    ok($semuaPending > (float) $afterTu['gu_menunggu_cair'] + 0.01,
        'ada baris menunggu cair ber-kantong TU yang tidak ikut dihitung');

    // ── 4. Proyeksi = saldo + GU menunggu cair ───────────────────────────
    echo "\n4. Proyeksi kas konsisten\n";
    ok(abs((float) $afterTu['proyeksi_kas_setelah_cair']
        - ((float) $afterTu['saldo_kas_saat_ini'] + (float) $afterTu['gu_menunggu_cair'])) < 0.01,
        'proyeksi = saldo kas + GU menunggu cair');

    // ── 5. Plafon UP/GU ditegakkan ───────────────────────────────────────
    echo "\n5. Dana UP/GU tidak boleh melebihi plafon\n";
    $plafon    = KasBank::DEFAULT_PLAFOND_UP;
    $ringkasan = $kas->getRingkasan($bulan, $tahun);

    // Plafon dinilai atas saldo + GU yang masih menunggu cair (komitmen),
    // karena keduanya sama-sama memakai jatah UP. Data riil sudah punya
    // komitmen, jadi ruang tersisa dihitung dari keadaan nyata.
    $komitmen = (float) $db->query("
        SELECT COALESCE(SUM(nominal), 0) FROM kas_bank
        WHERE status = 'menunggu_cair' AND arah = 'masuk' AND jenis_dana = 'UP_GU'
    ")->fetchColumn();
    $terpakai   = max(0.0, (float) $ringkasan['dana_gu_di_kas']) + $komitmen;
    $sisaRuang  = $plafon - $terpakai;
    printf("  dana_gu=%.0f komitmen=%.0f sisa_ruang=%.0f plafon=%.0f\n",
        $ringkasan['dana_gu_di_kas'], $komitmen, $sisaRuang, $plafon);

    $nominalLewat = max(1.0, $sisaRuang + 1000000.0);
    $ditolak = false;
    $pesan   = '';
    try {
        $id = $kas->create('2026-12-15', 'gu', 'SP2D-GU-LEBIH-' . $tag, 'TEST melebihi plafon', $nominalLewat, 1, 'cair', '2026-12-15', 'masuk', 'UP_GU');
        $createdKas[] = $id;
    } catch (RuntimeException $e) {
        $ditolak = true;
        $pesan   = $e->getMessage();
    }
    ok($ditolak, 'penerimaan UP/GU yang melewati plafon ditolak');
    ok(str_contains($pesan, 'plafon') && str_contains($pesan, 'TU'),
        'pesan menyebut plafon dan mengarahkan ke TU');
    echo "  Pesan: {$pesan}\n";

    // Dana TU tidak pernah kena plafon, walau nilainya besar.
    $idTuBesar = $kas->create('2026-12-16', 'tu', 'SP2D-TU-BESAR-' . $tag, 'TEST TU besar', $plafon * 2, 1, 'cair', '2026-12-16', 'masuk', 'TU');
    $createdKas[] = $idTuBesar;
    ok($idTuBesar > 0, 'dana TU tidak dibatasi plafon UP/GU');

    echo "\n5b. Plafon tidak bisa dilewati lewat jalur lain\n";

    // Jalur normal GU: diajukan dulu (menunggu_cair), cair belakangan.
    $ditolakPending = false;
    try {
        $idPending = $kas->create('2026-12-17', 'gu', 'SP2D-GU-PENDING-' . $tag, 'TEST pengajuan melebihi plafon', $nominalLewat, 1, 'menunggu_cair', null, 'masuk', 'UP_GU');
        $createdKas[] = $idPending;
    } catch (RuntimeException $e) {
        $ditolakPending = true;
    }
    ok($ditolakPending, 'pengajuan GU (menunggu_cair) yang melebihi plafon ditolak saat dicatat');

    // Dua pengajuan terpisah yang lolos sendiri-sendiri tidak boleh
    // bersama-sama melewati plafon.
    if ($sisaRuang > 2.0) {
        $separuh = $sisaRuang * 0.6;
        $idA = $kas->create('2026-12-18', 'gu', 'SP2D-GU-A-' . $tag, 'TEST komitmen A', $separuh, 1, 'menunggu_cair', null, 'masuk', 'UP_GU');
        $createdKas[] = $idA;
        $ditolakKomitmen = false;
        try {
            $idB = $kas->create('2026-12-18', 'gu', 'SP2D-GU-B-' . $tag, 'TEST komitmen B', $separuh, 1, 'menunggu_cair', null, 'masuk', 'UP_GU');
            $createdKas[] = $idB;
        } catch (RuntimeException $e) {
            $ditolakKomitmen = true;
        }
        ok($ditolakKomitmen, 'dua pengajuan menunggu cair tidak bisa melewati plafon bersama-sama');
    } else {
        echo "  [SKIP] ruang plafon tersisa terlalu kecil untuk uji komitmen ganda\n";
    }

    // Edit nominal dana UP/GU juga diperiksa.
    $idEdit = $kas->create('2026-12-19', 'gu', 'SP2D-GU-EDIT-' . $tag, 'TEST edit plafon', 0.0, 1, 'cair', '2026-12-19', 'masuk', 'TU');
    $createdKas[] = $idEdit;
    $ditolakEdit = false;
    try {
        // Ubah jadi dana UP/GU dengan nominal besar → harus ditolak.
        $kas->update($idEdit, '2026-12-19', 'gu', 'SP2D-GU-EDIT-' . $tag, 'TEST edit plafon', $nominalLewat, 'cair', '2026-12-19', 'masuk', 'UP_GU');
    } catch (RuntimeException $e) {
        $ditolakEdit = true;
    }
    ok($ditolakEdit, 'edit nominal dana UP/GU yang melewati plafon ditolak');

    // Mencairkan GU yang sudah dihitung sebagai komitmen tidak mengubah total
    // pemakaian plafon (komitmen turun, saldo naik) — jadi harus tetap boleh.
    // Bila plafon sudah terpakai penuh, pengajuan GU baru memang ditolak,
    // sehingga mekanisme ini hanya bisa diuji saat masih ada ruang.
    if ($sisaRuang > 1000.0) {
        $idCair = $kas->create('2026-12-20', 'gu', 'SP2D-GU-CAIR-' . $tag, 'TEST cair plafon', $sisaRuang * 0.5, 1, 'menunggu_cair', null, 'masuk', 'UP_GU');
        $createdKas[] = $idCair;
        $okCair = $kas->tandaiCair($idCair, '2026-12-20');
        ok($okCair === true, 'mencairkan GU yang sudah tercatat sebagai komitmen tetap boleh');
    } else {
        echo "  [SKIP] plafon sudah terpakai penuh, tidak ada ruang untuk GU baru\n";
    }

    // ── 6. Pengingat setor sisa TU 1 bulan ───────────────────────────────
    echo "\n6. Pengingat setor sisa TU\n";
    $lama = date('Y-m-d', strtotime('-45 days'));
    $insertTu->execute([
        ':nomor' => $tag . '-LAMA', ':seksi' => $seksiId, ':rekening' => $rekeningId,
        ':keperluan' => 'TEST lewat 1 bulan', ':status' => 'cair',
        ':digunakan' => 100000, ':disetor' => 0,
    ]);
    $tuLama = (int) $db->lastInsertId();
    $createdTu[] = $tuLama;
    $insertTuRek->execute([$tuLama, $rekeningId]);
    $db->prepare("UPDATE pengajuan_tu SET tanggal_cair = ? WHERE id = ?")->execute([$lama, $tuLama]);

    $insertTu->execute([
        ':nomor' => $tag . '-BARU', ':seksi' => $seksiId, ':rekening' => $rekeningId,
        ':keperluan' => 'TEST baru cair', ':status' => 'cair',
        ':digunakan' => 0, ':disetor' => 0,
    ]);
    $tuBaru = (int) $db->lastInsertId();
    $createdTu[] = $tuBaru;
    $insertTuRek->execute([$tuBaru, $rekeningId]);
    $db->prepare("UPDATE pengajuan_tu SET tanggal_cair = ? WHERE id = ?")->execute([date('Y-m-d'), $tuBaru]);

    $terlambat = $tu->getTuLewatBatasSetor(30);
    $idsTerlambat = array_map(fn($r) => (int) $r['id'], $terlambat);
    ok(in_array($tuLama, $idsTerlambat, true), 'TU cair 45 hari lalu muncul di pengingat');
    ok(!in_array($tuBaru, $idsTerlambat, true), 'TU yang baru cair tidak muncul di pengingat');

    $barisLama = null;
    foreach ($terlambat as $r) {
        if ((int) $r['id'] === $tuLama) { $barisLama = $r; break; }
    }
    ok($barisLama !== null && (float) $barisLama['sisa'] == 900000.0,
        'sisa TU = dana cair − terpakai (1jt − 100rb = 900rb)');
    ok($barisLama !== null && (int) $barisLama['umur_hari'] === 45, 'umur TU dihitung dari tanggal cair');

    echo "\nSEMUA TEST PEMISAHAN DANA UP/GU & TU LOLOS.\n";
} catch (Throwable $e) {
    echo "\n" . $e->getMessage() . "\n";
    exit(1);
} finally {
    foreach ($createdTrx as $id) {
        $db->prepare('DELETE FROM transaksi WHERE id = ?')->execute([$id]);
    }
    foreach ($createdTu as $id) {
        $db->prepare('DELETE FROM pengajuan_tu_rekening WHERE pengajuan_tu_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM pengajuan_tu WHERE id = ?')->execute([$id]);
    }
    foreach ($createdKas as $id) {
        $db->prepare('DELETE FROM kas_bank WHERE id = ?')->execute([$id]);
    }
    echo "Cleanup selesai.\n";
}
