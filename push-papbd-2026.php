<?php

declare(strict_types=1);

/**
 * PUSH PAPBD 2026 + RAK ke server produksi — AMAN untuk transaksi berjalan.
 *
 * Jaminan keamanan:
 *  - Tabel `transaksi` HANYA di-SELECT (untuk guard realisasi). TIDAK PERNAH
 *    di-INSERT/UPDATE/DELETE oleh skrip ini (tidak ada statement tulis ke transaksi).
 *  - Pencocokan by KODE (kode_sub_kegiatan + kode_rekening + tahun), BUKAN by ID,
 *    karena ID dev dan produksi bisa berbeda.
 *  - Guard realisasi: pagu yang diturunkan di bawah realisasi terverifikasi
 *    DITOLAK per rekening (dilaporkan, dilewati).
 *  - RAK hanya ditulis untuk rekening yang pagunya berubah ATAU RAK-nya
 *    terdefinisi di dataset. RAK lokal produksi di luar itu TIDAK disentuh.
 *  - Backup otomatis tabel pagu/rak/rekening/sub_kegiatan sebelum eksekusi.
 *
 * Cara pakai:
 *  - Simulasi (disarankan dulu):  php push-papbd-2026.php
 *  - Eksekusi:                    php push-papbd-2026.php --execute
 *  - Via browser (localhost saja): push-papbd-2026.php / ?execute=1
 */

define('PAPBD_PUSH_RUNNER', true);

$isCli = PHP_SAPI === 'cli';
$execute = $isCli
    ? in_array('--execute', $argv ?? [], true)
    : (isset($_GET['execute']) && $_GET['execute'] === '1');

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Push PAPBD 2026</title></head>";
    echo "<body style='background:#0f172a;color:#f8fafc;font-family:Consolas,monospace;padding:24px;'>";
    echo '<h2 style="font-family:sans-serif;">Push PAPBD 2026 + RAK (aman transaksi)</h2>';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        echo '<div>Akses web ditolak. Jalankan via CLI.</div></body></html>';
        exit(1);
    }
}

function papbd_log(string $msg, string $type = 'info'): void
{
    global $isCli;
    if ($isCli) {
        $prefix = match ($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            'skip'    => '[SKIP] ',
            default   => '[INFO] ',
        };
        echo $prefix . $msg . PHP_EOL;
        return;
    }
    $color = match ($type) {
        'success' => '#34d399',
        'error'   => '#f87171',
        'warning' => '#fbbf24',
        'skip'    => '#94a3b8',
        default   => '#60a5fa',
    };
    echo '<div style="color:' . $color . ';margin:6px 0;background:#1e293b;padding:8px 12px;border-radius:6px;">'
        . '[' . strtoupper($type) . '] ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
}

require_once __DIR__ . '/config/load_env.php';

$dataFile = __DIR__ . '/database/papbd/papbd_data_2026.php';
if (!is_file($dataFile)) {
    papbd_log('Dataset tidak ditemukan: database/papbd/papbd_data_2026.php', 'error');
    exit(1);
}
/** @var array $data */
$data = require $dataFile;
$tahun = (int) ($data['meta']['tahun'] ?? 2026);

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $name = $_ENV['DB_NAME'] ?? 'db_anggaran';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    papbd_log('Koneksi DB gagal: ' . $e->getMessage(), 'error');
    exit(1);
}

$rp = static fn(float $n): string => 'Rp ' . number_format($n, 0, ',', '.');

// ---- 0. Preconditions ----
$needTables = ['program', 'kegiatan', 'sub_kegiatan', 'rekening', 'pagu', 'rak', 'transaksi', 'pagu_riwayat'];
foreach ($needTables as $t) {
    $chk = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $chk->execute([$t]);
    if ((int) $chk->fetchColumn() === 0) {
        papbd_log("Tabel `$t` tidak ada. " . ($t === 'pagu_riwayat'
            ? 'Jalankan dulu: php migrate.php (atau deploy-git.bat).'
            : 'Struktur DB tidak cocok.'), 'error');
        exit(1);
    }
}
papbd_log('Mode: ' . ($execute ? 'EKSEKUSI (menulis DB)' : 'DRY-RUN (simulasi, tidak menulis)') . ' | DB=' . $name . " | tahun=$tahun");

// ---- Helpers (hanya SELECT ke transaksi) ----
function realisasi(PDO $pdo, int $rekeningId, int $tahun): float
{
    try {
        $s = $pdo->prepare("SELECT COALESCE(SUM(nilai),0) FROM transaksi WHERE rekening_id = :rid AND YEAR(tanggal) = :thn AND status = 'diverifikasi'");
        $s->execute([':rid' => $rekeningId, ':thn' => $tahun]);
        return (float) $s->fetchColumn();
    } catch (Throwable) {
        $s = $pdo->prepare('SELECT COALESCE(SUM(nilai),0) FROM transaksi WHERE rekening_id = :rid AND YEAR(tanggal) = :thn');
        $s->execute([':rid' => $rekeningId, ':thn' => $tahun]);
        return (float) $s->fetchColumn();
    }
}

function find_program(PDO $pdo, string $kode, string $nama): ?array
{
    $s = $pdo->prepare('SELECT * FROM program WHERE kode_program = ? LIMIT 1');
    $s->execute([$kode]);
    if ($r = $s->fetch()) { return [$r, 'exact']; }
    $s = $pdo->prepare('SELECT * FROM program WHERE nama_program = ? LIMIT 1');
    $s->execute([$nama]);
    if ($r = $s->fetch()) { return [$r, 'nama']; }
    return null;
}

function find_kegiatan(PDO $pdo, int $programId, string $kode, string $nama): ?array
{
    $s = $pdo->prepare('SELECT * FROM kegiatan WHERE program_id = ? AND kode_kegiatan = ? LIMIT 1');
    $s->execute([$programId, $kode]);
    if ($r = $s->fetch()) { return [$r, 'exact']; }
    $s = $pdo->prepare('SELECT * FROM kegiatan WHERE program_id = ? AND nama_kegiatan = ? LIMIT 1');
    $s->execute([$programId, $nama]);
    if ($r = $s->fetch()) { return [$r, 'nama']; }
    return null;
}

function find_sub(PDO $pdo, int $kegiatanId, string $kode, string $nama): ?array
{
    $s = $pdo->prepare('SELECT * FROM sub_kegiatan WHERE kegiatan_id = ? AND kode_sub_kegiatan = ? LIMIT 1');
    $s->execute([$kegiatanId, $kode]);
    if ($r = $s->fetch()) { return [$r, 'exact']; }
    $s = $pdo->prepare('SELECT * FROM sub_kegiatan WHERE kegiatan_id = ? AND nama_sub_kegiatan = ? LIMIT 1');
    $s->execute([$kegiatanId, $nama]);
    if ($r = $s->fetch()) { return [$r, 'nama']; }
    return null;
}

function find_rekening(PDO $pdo, int $subId, string $kode, string $nama): ?array
{
    $s = $pdo->prepare('SELECT * FROM rekening WHERE sub_kegiatan_id = ? AND kode_rekening = ? LIMIT 1');
    $s->execute([$subId, $kode]);
    if ($r = $s->fetch()) { return [$r, 'exact']; }
    $s = $pdo->prepare('SELECT * FROM rekening WHERE sub_kegiatan_id = ? AND nama_rekening = ? LIMIT 1');
    $s->execute([$subId, $nama]);
    if ($r = $s->fetch()) { return [$r, 'nama']; }
    return null;
}

// Kelompokkan RAK dataset per (kode_sub, kode_rekening)
$rakMap = [];
foreach ($data['rak'] as $rk) {
    $rakMap[$rk['kode_sub']][$rk['kode_rekening']][(int) $rk['bulan']] = (float) $rk['nilai'];
}

$stat = ['pagu_sama' => 0, 'pagu_ubah' => 0, 'pagu_buat' => 0, 'pagu_tolak' => 0,
    'rek_buat' => 0, 'rak_tulis' => 0, 'rak_skip' => 0, 'butuh_tindakan' => 0];
$needAction = [];

// ---- 1. Backup (execute only) ----
$bakSuffix = date('Ymd_His');
if ($execute) {
    foreach (['pagu', 'rak', 'rekening', 'sub_kegiatan'] as $t) {
        $bak = "{$t}_bak_{$bakSuffix}";
        $pdo->exec("CREATE TABLE `$bak` AS SELECT * FROM `$t`");
        papbd_log("Backup dibuat: `$bak`", 'success');
    }
    papbd_log('Restore bila perlu: RENAME TABLE `pagu` TO `pagu_gagal`, `pagu_bak_' . $bakSuffix . '` TO `pagu`; dst.', 'warning');
}

// ---- 2. Rename struktural (execute only; dry-run mensimulasikan) ----
foreach ($data['renames']['sub'] as $rn) {
    $old = $pdo->prepare('SELECT id FROM sub_kegiatan WHERE kode_sub_kegiatan = ?');
    $old->execute([$rn['old']]);
    $oldId = $old->fetchColumn();
    $new = $pdo->prepare('SELECT id FROM sub_kegiatan WHERE kode_sub_kegiatan = ?');
    $new->execute([$rn['new']]);
    $newId = $new->fetchColumn();
    if ($newId) {
        papbd_log("Sub {$rn['new']} sudah ada (rename {$rn['old']} dianggap selesai)", 'skip');
    } elseif ($oldId) {
        if ($execute) {
            $pdo->prepare('UPDATE sub_kegiatan SET kode_sub_kegiatan = ? WHERE id = ?')->execute([$rn['new'], $oldId]);
            papbd_log("Sub rename: {$rn['old']} -> {$rn['new']}", 'success');
        } else {
            papbd_log("Sub rename (simulasi): {$rn['old']} -> {$rn['new']}");
        }
    } else {
        $needAction[] = "Sub {$rn['old']} / {$rn['new']} tidak ditemukan di produksi";
        $stat['butuh_tindakan']++;
    }
}
foreach ($data['renames']['rekening'] as $rn) {
    $s = $pdo->prepare('SELECT id FROM sub_kegiatan WHERE kode_sub_kegiatan = ?');
    $s->execute([$rn['sub']]);
    $subId = $s->fetchColumn();
    if (!$subId) {
        $needAction[] = "Sub {$rn['sub']} (untuk rename rekening {$rn['old']}) tidak ditemukan";
        $stat['butuh_tindakan']++;
        continue;
    }
    $chkNew = $pdo->prepare('SELECT id FROM rekening WHERE sub_kegiatan_id = ? AND kode_rekening = ?');
    $chkNew->execute([$subId, $rn['new']]);
    if ($chkNew->fetchColumn()) {
        papbd_log("Rekening {$rn['new']} sudah ada (rename dianggap selesai)", 'skip');
        continue;
    }
    $chkOld = $pdo->prepare('SELECT id FROM rekening WHERE sub_kegiatan_id = ? AND kode_rekening = ?');
    $chkOld->execute([$subId, $rn['old']]);
    $oldId = $chkOld->fetchColumn();
    if ($oldId) {
        if ($execute) {
            $pdo->prepare('UPDATE rekening SET kode_rekening = ? WHERE id = ?')->execute([$rn['new'], $oldId]);
            papbd_log("Rekening rename: {$rn['old']} -> {$rn['new']}", 'success');
        } else {
            papbd_log("Rekening rename (simulasi): {$rn['old']} -> {$rn['new']}");
        }
    } else {
        papbd_log("Rename rekening dilewati (old & new tidak ada, mungkin sudah beres): {$rn['old']}", 'skip');
    }
}

// ---- 3. Upsert pagu + RAK ----
foreach ($data['pagu'] as $row) {
    $label = "{$row['kode_sub']} / {$row['kode_rekening']}";
    $fp = find_program($pdo, $row['kode_program'], $row['nama_program']);
    if (!$fp) { $needAction[] = "$label: program {$row['kode_program']} tidak ada"; $stat['butuh_tindakan']++; continue; }
    [$prog, $mProg] = $fp;
    $fk = find_kegiatan($pdo, (int) $prog['id'], $row['kode_kegiatan'], $row['nama_kegiatan']);
    if (!$fk) { $needAction[] = "$label: kegiatan {$row['kode_kegiatan']} tidak ada"; $stat['butuh_tindakan']++; continue; }
    [$keg, $mKeg] = $fk;
    $fs = find_sub($pdo, (int) $keg['id'], $row['kode_sub'], $row['nama_sub']);
    if (!$fs) { $needAction[] = "$label: sub {$row['kode_sub']} tidak ada"; $stat['butuh_tindakan']++; continue; }
    [$sub, $mSub] = $fs;
    $fr = find_rekening($pdo, (int) $sub['id'], $row['kode_rekening'], $row['nama_rekening']);
    $fuzzy = ($mProg !== 'exact' || $mKeg !== 'exact' || $mSub !== 'exact') ? ' [FUZZY by nama]' : '';

    $target = (float) $row['nilai'];
    $devRak = $rakMap[$row['kode_sub']][$row['kode_rekening']] ?? [];

    if (!$fr) {
        // Buat rekening + pagu baru (butuh sub resolved)
        if ($execute) {
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO rekening (sub_kegiatan_id, kode_rekening, nama_rekening) VALUES (?,?,?)');
                $ins->execute([(int) $sub['id'], $row['kode_rekening'], $row['nama_rekening']]);
                $newRid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO pagu (rekening_id, tahun, nilai_pagu) VALUES (?,?,?)')
                    ->execute([$newRid, $tahun, $target]);
                if (!empty($devRak)) {
                    $ir = $pdo->prepare('INSERT INTO rak (rekening_id, tahun, bulan, nilai_rak) VALUES (?,?,?,?)');
                    foreach ($devRak as $bl => $nl) { if ($nl > 0) { $ir->execute([$newRid, $tahun, $bl, $nl]); } }
                    $stat['rak_tulis']++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                papbd_log("GAGAL $label$fuzzy: " . $e->getMessage() . ' (dilewati, lanjut item berikut)', 'error');
                $needAction[] = "$label: gagal create (" . $e->getMessage() . ')';
                $stat['butuh_tindakan']++;
                continue;
            }
            papbd_log("CREATE $label$fuzzy pagu " . $rp($target), 'success');
            $stat['rek_buat']++; $stat['pagu_buat']++;
        } else {
            papbd_log("CREATE (simulasi) $label$fuzzy pagu " . $rp($target));
            $stat['rek_buat']++; $stat['pagu_buat']++;
        }
        continue;
    }
    [$rek, $mRek] = $fr;
    if ($mRek !== 'exact') { $fuzzy .= ' [rek by nama]'; }
    $rid = (int) $rek['id'];

    $pg = $pdo->prepare('SELECT id, nilai_pagu FROM pagu WHERE rekening_id = ? AND tahun = ? ORDER BY id ASC');
    $pg->execute([$rid, $tahun]);
    $prows = $pg->fetchAll();
    if (count($prows) > 1) {
        papbd_log("$label: ada " . count($prows) . " baris pagu duplikat, pakai id terkecil", 'warning');
    }
    $pid = isset($prows[0]) ? (int) $prows[0]['id'] : null;
    $lama = isset($prows[0]) ? (float) $prows[0]['nilai_pagu'] : null;

    $paguChanged = false;
    if ($pid === null) {
        if ($execute) {
            $pdo->prepare('INSERT INTO pagu (rekening_id, tahun, nilai_pagu) VALUES (?,?,?)')->execute([$rid, $tahun, $target]);
            papbd_log("PAGU BARU $label$fuzzy " . $rp($target), 'success');
            $stat['pagu_buat']++;
        } else {
            papbd_log("PAGU BARU (simulasi) $label$fuzzy " . $rp($target));
            $stat['pagu_buat']++;
        }
        $paguChanged = true;
    } elseif (abs($lama - $target) < 0.01) {
        $stat['pagu_sama']++;
    } else {
        $real = realisasi($pdo, $rid, $tahun);
        if ($target + 0.009 < $real) {
            papbd_log("DITOLAK $label$fuzzy: target " . $rp($target) . " < realisasi " . $rp($real) . ' (dilewati, pagu prod tetap ' . $rp($lama) . ')', 'error');
            $stat['pagu_tolak']++;
        } elseif ($execute) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE pagu SET nilai_pagu = ? WHERE id = ?')->execute([$target, $pid]);
                $pdo->prepare('INSERT INTO pagu_riwayat (pagu_id, rekening_id, tahun, nilai_sebelum, nilai_sesudah, selisih, jenis, keterangan, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$pid, $rid, $tahun, $lama, $target, $target - $lama, 'PAPBD', 'PAPBD 2026 - push dari dataset dev', null]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                papbd_log("GAGAL $label$fuzzy: " . $e->getMessage() . ' (dilewati, lanjut item berikut)', 'error');
                $needAction[] = "$label: gagal update (" . $e->getMessage() . ')';
                $stat['butuh_tindakan']++;
                continue;
            }
            papbd_log("PAGU $label$fuzzy " . $rp($lama) . ' -> ' . $rp($target), 'success');
            $stat['pagu_ubah']++;
            $paguChanged = true;
        } else {
            papbd_log("PAGU (simulasi) $label$fuzzy " . $rp($lama) . ' -> ' . $rp($target));
            $stat['pagu_ubah']++;
            $paguChanged = true;
        }
    }

    // RAK: tulis jika terdefinisi di dataset; kosongkan jika pagu berubah; lewati sisanya
    if (!empty($devRak)) {
        if ($execute) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM rak WHERE rekening_id = ? AND tahun = ?')->execute([$rid, $tahun]);
                $ir = $pdo->prepare('INSERT INTO rak (rekening_id, tahun, bulan, nilai_rak) VALUES (?,?,?,?)');
                foreach ($devRak as $bl => $nl) { if ($nl > 0) { $ir->execute([$rid, $tahun, $bl, $nl]); } }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                papbd_log("GAGAL RAK $label$fuzzy: " . $e->getMessage(), 'error');
                $needAction[] = "$label: gagal tulis RAK (" . $e->getMessage() . ')';
                $stat['butuh_tindakan']++;
                continue;
            }
            $stat['rak_tulis']++;
        } else {
            $stat['rak_tulis']++;
        }
    } elseif ($paguChanged) {
        if ($execute) {
            $pdo->prepare('DELETE FROM rak WHERE rekening_id = ? AND tahun = ?')->execute([$rid, $tahun]);
            papbd_log("RAK dikosongkan (pagu 0): $label", 'success');
        }
        $stat['rak_tulis']++;
    } else {
        $stat['rak_skip']++;
    }
}

// ---- 4. Verifikasi total ----
papbd_log('--- HASIL ' . ($execute ? 'EKSEKUSI' : 'SIMULASI') . ' ---');
foreach ($stat as $k => $v) { papbd_log(sprintf('%-16s : %d', $k, $v)); }
if (!empty($needAction)) {
    papbd_log('BUTUH TINDAKAN MANUAL (' . count($needAction) . '):', 'warning');
    foreach (array_slice($needAction, 0, 50) as $m) { papbd_log('- ' . $m, 'warning'); }
}
papbd_log('--- VERIFIKASI TOTAL (DB produksi) ---');
$grand = 0.0;
$progRows = $pdo->query('SELECT id, kode_program FROM program ORDER BY kode_program')->fetchAll();
foreach ($progRows as $pr) {
    $s = $pdo->prepare("SELECT COALESCE(SUM(pg.nilai_pagu),0) FROM pagu pg INNER JOIN rekening r ON r.id=pg.rekening_id
      INNER JOIN sub_kegiatan sk ON sk.id=r.sub_kegiatan_id INNER JOIN kegiatan k ON k.id=sk.kegiatan_id
      WHERE k.program_id = ? AND pg.tahun = ?");
    $s->execute([(int) $pr['id'], $tahun]);
    $v = (float) $s->fetchColumn();
    $grand += $v;
    $k3 = implode('.', array_slice(explode('.', $pr['kode_program']), 0, 3));
    $exp = $data['meta']['expected_totals'][$k3] ?? null;
    $mark = ($exp !== null && abs($v - $exp) < 0.01) ? 'COCOK' : ($exp !== null ? 'SELISIH (ekspektasi ' . $rp((float) $exp) . ')' : 'di luar dataset');
    papbd_log("{$pr['kode_program']} total " . $rp($v) . " => $mark", $mark === 'COCOK' ? 'success' : 'warning');
}
$expTot = (float) ($data['meta']['expected_totals']['TOTAL'] ?? 0);
papbd_log('TOTAL SKPD ' . $rp($grand) . ' (ekspektasi ' . $rp($expTot) . ') ' . (abs($grand - $expTot) < 0.01 ? 'COCOK' : 'SELISIH'), abs($grand - $expTot) < 0.01 ? 'success' : 'warning');
papbd_log('Catatan: tabel `transaksi` tidak ditulis sama sekali oleh skrip ini (hanya SELECT realisasi).');

if (!$isCli) { echo '</body></html>'; }
