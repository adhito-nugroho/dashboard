<?php

declare(strict_types=1);

/**
 * Import hasil tools/rka_extract.py ke tabel yang sudah ada:
 * program -> kegiatan -> sub_kegiatan -> rekening -> pagu (+ pagu_riwayat).
 *
 * Dry-run (default, semua perubahan di-rollback):
 *   php tools/import_rka.php rka_import.json
 * Terapkan:
 *   php tools/import_rka.php rka_import.json --apply
 *
 * Opsi:
 *   --seksi=ID            paksa seksi untuk sub kegiatan baru
 *   --map=mapping.json    override per kode sub kegiatan, {"3.28.03.1.04.0002": 3}
 *   --tag-only            hanya update tagar rekening yang sudah ada (pagu tidak disentuh)
 *   --jenis=PAPBD         jenis pagu_riwayat (PAPBD/KOREKSI/APBD)
 *   --keterangan="..."    keterangan pagu_riwayat
 *
 * Seksi default untuk sub kegiatan baru: 3.28.01 -> TU, 3.28.03 -> TKUK,
 * 3.28.05 -> RLPM. Sub kegiatan yang sudah ada tidak diubah seksinya.
 */

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Hanya bisa dijalankan dari CLI.\n");
    exit(1);
}

$jsonPath = null;
$apply = false;
$tagOnly = false;
$seksiOverride = null;
$jenisRiwayat = 'PAPBD';
$keteranganRiwayat = 'Import RKA';
$mapPath = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--tag-only') {
        $tagOnly = true;
    } elseif (str_starts_with($arg, '--seksi=')) {
        $seksiOverride = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--jenis=')) {
        $jenisRiwayat = substr($arg, 8) ?: 'PAPBD';
    } elseif (str_starts_with($arg, '--keterangan=')) {
        $keteranganRiwayat = substr($arg, 13) ?: 'Import RKA';
    } elseif (str_starts_with($arg, '--map=')) {
        $mapPath = substr($arg, 6);
    } elseif ($arg !== '' && $arg[0] !== '-') {
        $jsonPath = $arg;
    }
}

if ($jsonPath === null || !is_file($jsonPath)) {
    fwrite(STDERR, "Pemakaian: php tools/import_rka.php <rka_import.json> [--apply] [--seksi=ID] [--map=file.json]\n");
    exit(1);
}

$items = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($items)) {
    fwrite(STDERR, "JSON tidak valid: {$jsonPath}\n");
    exit(1);
}

$db = Database::getConnection();

$seksiByKode = [];
foreach ($db->query('SELECT id, kode_seksi FROM seksi') as $row) {
    $seksiByKode[strtoupper((string) $row['kode_seksi'])] = (int) $row['id'];
}

$mapSubSeksi = [];
if ($mapPath !== null && is_file($mapPath)) {
    $decoded = json_decode((string) file_get_contents($mapPath), true);
    if (is_array($decoded)) {
        $mapSubSeksi = $decoded;
    }
}

$hasRiwayat = false;
try {
    $hasRiwayat = (bool) $db->query("SHOW TABLES LIKE 'pagu_riwayat'")->fetchColumn();
} catch (Throwable $e) {
    $hasRiwayat = false;
}

$stats = [
    'program_create' => 0, 'program_update' => 0,
    'kegiatan_create' => 0, 'kegiatan_update' => 0,
    'sub_create' => 0, 'sub_update' => 0,
    'rekening_create' => 0, 'rekening_update' => 0,
    'pagu_create' => 0, 'pagu_update' => 0, 'pagu_same' => 0,
    'files' => 0, 'warnings' => 0,
];

/**
 * @param array<string,int> $seksiByKode
 */
function defaultSeksiId(string $kodeProgram, array $seksiByKode): ?int
{
    if (str_starts_with($kodeProgram, '3.28.01')) {
        return $seksiByKode['TU'] ?? null;
    }
    if (str_starts_with($kodeProgram, '3.28.03')) {
        return $seksiByKode['TKUK'] ?? null;
    }
    if (str_starts_with($kodeProgram, '3.28.05')) {
        return $seksiByKode['RLPM'] ?? null;
    }
    return null;
}

function upsertProgram(PDO $db, array $program, int $tahun, array &$stats, array &$log, bool $create = true): int
{
    $stmt = $db->prepare('SELECT id, nama_program FROM program WHERE kode_program = ? AND tahun = ? LIMIT 1');
    $stmt->execute([$program['kode'], $tahun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($create && trim((string) $row['nama_program']) !== trim((string) $program['nama'])) {
            $db->prepare('UPDATE program SET nama_program = ? WHERE id = ?')
                ->execute([$program['nama'], $row['id']]);
            $stats['program_update']++;
            $log[] = "[update] program {$program['kode']}";
        }
        return (int) $row['id'];
    }

    if (!$create) {
        return 0;
    }

    $db->prepare('INSERT INTO program (kode_program, nama_program, tahun) VALUES (?, ?, ?)')
        ->execute([$program['kode'], $program['nama'], $tahun]);
    $stats['program_create']++;
    $log[] = "[create] program {$program['kode']} - {$program['nama']}";
    return (int) $db->lastInsertId();
}

function upsertKegiatan(PDO $db, int $programId, array $kegiatan, array &$stats, array &$log, bool $create = true): int
{
    $stmt = $db->prepare('SELECT id, nama_kegiatan FROM kegiatan WHERE program_id = ? AND kode_kegiatan = ? LIMIT 1');
    $stmt->execute([$programId, $kegiatan['kode']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($create && trim((string) $row['nama_kegiatan']) !== trim((string) $kegiatan['nama'])) {
            $db->prepare('UPDATE kegiatan SET nama_kegiatan = ? WHERE id = ?')
                ->execute([$kegiatan['nama'], $row['id']]);
            $stats['kegiatan_update']++;
            $log[] = "[update] kegiatan {$kegiatan['kode']}";
        }
        return (int) $row['id'];
    }

    if (!$create) {
        return 0;
    }

    $db->prepare('INSERT INTO kegiatan (program_id, kode_kegiatan, nama_kegiatan) VALUES (?, ?, ?)')
        ->execute([$programId, $kegiatan['kode'], $kegiatan['nama']]);
    $stats['kegiatan_create']++;
    $log[] = "[create] kegiatan {$kegiatan['kode']} - {$kegiatan['nama']}";
    return (int) $db->lastInsertId();
}

function upsertSubKegiatan(
    PDO $db,
    int $kegiatanId,
    array $sub,
    ?int $seksiId,
    array &$stats,
    array &$log,
    bool $create = true
): int {
    $stmt = $db->prepare('SELECT id, nama_sub_kegiatan FROM sub_kegiatan WHERE kegiatan_id = ? AND kode_sub_kegiatan = ? LIMIT 1');
    $stmt->execute([$kegiatanId, $sub['kode']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($create && trim((string) $row['nama_sub_kegiatan']) !== trim((string) $sub['nama'])) {
            $db->prepare('UPDATE sub_kegiatan SET nama_sub_kegiatan = ? WHERE id = ?')
                ->execute([$sub['nama'], $row['id']]);
            $stats['sub_update']++;
            $log[] = "[update] sub_kegiatan {$sub['kode']}";
        }
        return (int) $row['id'];
    }

    if (!$create) {
        return 0;
    }

    $db->prepare('INSERT INTO sub_kegiatan (kegiatan_id, seksi_id, kode_sub_kegiatan, nama_sub_kegiatan) VALUES (?, ?, ?, ?)')
        ->execute([$kegiatanId, $seksiId, $sub['kode'], $sub['nama']]);
    $stats['sub_create']++;
    $log[] = "[create] sub_kegiatan {$sub['kode']} - {$sub['nama']}"
        . ($seksiId === null ? ' (seksi belum diisi!)' : " (seksi {$seksiId})");
    return (int) $db->lastInsertId();
}

function upsertRekening(PDO $db, int $subId, array $rek, array &$stats, array &$log, bool $tagOnly = false): int
{
    $tagar = trim((string) ($rek['tagar'] ?? ''));
    $stmt = $db->prepare('SELECT id, kode_rekening, nama_rekening, tagar FROM rekening WHERE sub_kegiatan_id = ? AND kode_rekening = ? LIMIT 1');
    $stmt->execute([$subId, $rek['kode']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Fallback: cocokkan nama (kode lama vs format SIPD baru).
        $stmt = $db->prepare('SELECT id, kode_rekening, nama_rekening, tagar FROM rekening WHERE sub_kegiatan_id = ? AND TRIM(nama_rekening) = ? LIMIT 1');
        $stmt->execute([$subId, trim((string) $rek['nama'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($row) {
        if ($tagOnly) {
            // Tag-only: jangan ubah kode/nama rekening.
            if ($tagar !== '' && trim((string) ($row['tagar'] ?? '')) !== $tagar) {
                $db->prepare('UPDATE rekening SET tagar = ? WHERE id = ?')->execute([$tagar, $row['id']]);
                $stats['rekening_update']++;
                $log[] = "[update] tagar {$rek['kode']} = {$tagar}";
            }
            return (int) $row['id'];
        }

        $updates = [];
        $params = [];
        if ((string) $row['kode_rekening'] !== (string) $rek['kode']) {
            $updates[] = 'kode_rekening = ?';
            $params[] = $rek['kode'];
        }
        if (trim((string) $row['nama_rekening']) !== trim((string) $rek['nama'])) {
            $updates[] = 'nama_rekening = ?';
            $params[] = $rek['nama'];
        }
        // Jangan menghapus tagar lama bila RKA ini tidak punya tagar.
        if ($tagar !== '' && trim((string) ($row['tagar'] ?? '')) !== $tagar) {
            $updates[] = 'tagar = ?';
            $params[] = $tagar;
            $log[] = "[update] tagar {$rek['kode']} = {$tagar}";
        }
        if ($updates !== []) {
            $params[] = $row['id'];
            $db->prepare('UPDATE rekening SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
            $stats['rekening_update']++;
            $log[] = "[update] rekening {$rek['kode']} ({$rek['nama']})";
        }
        return (int) $row['id'];
    }

    if ($tagOnly) {
        $log[] = "[skip] rekening {$rek['kode']} belum ada (tag-only tidak membuat rekening baru)";
        return 0;
    }

    $db->prepare('INSERT INTO rekening (sub_kegiatan_id, kode_rekening, nama_rekening, tagar) VALUES (?, ?, ?, ?)')
        ->execute([$subId, $rek['kode'], $rek['nama'], $tagar !== '' ? $tagar : null]);
    $stats['rekening_create']++;
    $log[] = "[create] rekening {$rek['kode']} - {$rek['nama']}"
        . ($tagar !== '' ? " [{$tagar}]" : '');
    return (int) $db->lastInsertId();
}

function upsertPagu(
    PDO $db,
    int $rekeningId,
    int $tahun,
    float $nilai,
    bool $hasRiwayat,
    string $jenisRiwayat,
    string $keterangan,
    array &$stats,
    array &$log,
    string $kodeRekening
): void {
    $stmt = $db->prepare('SELECT id, nilai_pagu FROM pagu WHERE rekening_id = ? AND tahun = ? LIMIT 1');
    $stmt->execute([$rekeningId, $tahun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $db->prepare('INSERT INTO pagu (rekening_id, tahun, nilai_pagu) VALUES (?, ?, ?)')
            ->execute([$rekeningId, $tahun, $nilai]);
        $stats['pagu_create']++;
        $log[] = sprintf('[create] pagu %s = %s', $kodeRekening, number_format($nilai, 0, ',', '.'));
        return;
    }

    $sebelum = (float) $row['nilai_pagu'];
    if (abs($nilai - $sebelum) < 0.009) {
        $stats['pagu_same']++;
        return;
    }

    $db->prepare('UPDATE pagu SET nilai_pagu = ? WHERE id = ?')->execute([$nilai, $row['id']]);
    $stats['pagu_update']++;
    $log[] = sprintf(
        '[update] pagu %s: %s -> %s',
        $kodeRekening,
        number_format($sebelum, 0, ',', '.'),
        number_format($nilai, 0, ',', '.')
    );

    if ($hasRiwayat) {
        try {
            $db->prepare('
                INSERT INTO pagu_riwayat
                    (pagu_id, rekening_id, tahun, nilai_sebelum, nilai_sesudah, selisih, jenis, keterangan, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
            ')->execute([
                $row['id'],
                $rekeningId,
                $tahun,
                $sebelum,
                $nilai,
                $nilai - $sebelum,
                $jenisRiwayat,
                $keterangan,
            ]);
        } catch (Throwable $e) {
            $log[] = '[warn] gagal mencatat pagu_riwayat: ' . $e->getMessage();
        }
    }
}

echo ($apply ? "MODE: APPLY" : "MODE: DRY-RUN (semua perubahan di-rollback)")
    . ($tagOnly ? " [TAG-ONLY]" : "") . "\n";

$db->beginTransaction();
try {
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['tahun'], $item['program'], $item['kegiatan'], $item['sub_kegiatan'], $item['rekening'])) {
            fwrite(STDERR, "[skip] entri JSON tidak lengkap\n");
            continue;
        }

        $tahun = (int) $item['tahun'];
        if ($tahun < 2000 || $tahun > 2100) {
            fwrite(STDERR, "[skip] tahun tidak valid: {$tahun}\n");
            continue;
        }

        $stats['files']++;
        $log = [];
        $subCode = (string) $item['sub_kegiatan']['kode'];
        $seksiId = $mapSubSeksi[$subCode]
            ?? ($seksiOverride ?? defaultSeksiId((string) $item['program']['kode'], $seksiByKode));
        $seksiId = $seksiId !== null ? (int) $seksiId : null;

        echo "\n== {$subCode} — {$item['sub_kegiatan']['nama']}\n";
        if (!empty($item['file'])) {
            echo "   file: " . basename((string) $item['file']) . "\n";
        }

        $create = !$tagOnly;
        $programId = upsertProgram($db, $item['program'], $tahun, $stats, $log, $create);
        $kegiatanId = $programId > 0
            ? upsertKegiatan($db, $programId, $item['kegiatan'], $stats, $log, $create)
            : 0;
        $subId = $kegiatanId > 0
            ? upsertSubKegiatan($db, $kegiatanId, $item['sub_kegiatan'], $seksiId, $stats, $log, $create)
            : 0;

        if ($programId <= 0 || $kegiatanId <= 0 || $subId <= 0) {
            $log[] = '[skip] program/kegiatan/sub kegiatan belum ada di DB';
        } else {
            foreach ($item['rekening'] as $rek) {
                if (!isset($rek['kode'], $rek['nama'], $rek['nilai'])) {
                    continue;
                }
                $rekId = upsertRekening($db, $subId, $rek, $stats, $log, $tagOnly);
                if ($rekId <= 0) {
                    continue;
                }
                if (!$tagOnly) {
                    upsertPagu(
                        $db,
                        $rekId,
                        $tahun,
                        (float) $rek['nilai'],
                        $hasRiwayat,
                        $jenisRiwayat,
                        $keteranganRiwayat,
                        $stats,
                        $log,
                        (string) $rek['kode']
                    );
                }
            }
        }

        foreach ((array) ($item['warnings'] ?? []) as $warning) {
            $stats['warnings']++;
            $log[] = '[warn] ' . $warning;
        }

        foreach ($log as $line) {
            echo '   ' . $line . "\n";
        }
        if ($log === []) {
            echo "   (tidak ada perubahan)\n";
        }
    }

    if ($apply) {
        $db->commit();
    } else {
        $db->rollBack();
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "\nGAGAL: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n=== RINGKASAN ===\n";
echo "File: {$stats['files']}\n";
echo "Program : +{$stats['program_create']} / ~{$stats['program_update']}\n";
echo "Kegiatan: +{$stats['kegiatan_create']} / ~{$stats['kegiatan_update']}\n";
echo "Sub     : +{$stats['sub_create']} / ~{$stats['sub_update']}\n";
echo "Rekening: +{$stats['rekening_create']} / ~{$stats['rekening_update']}\n";
echo "Pagu    : +{$stats['pagu_create']} / ~{$stats['pagu_update']} / ={$stats['pagu_same']} (sama)\n";
if ($stats['warnings'] > 0) {
    echo "Warning : {$stats['warnings']}\n";
}
echo $apply ? "STATUS: diterapkan.\n" : "STATUS: dry-run, tidak ada data yang diubah. Jalankan dengan --apply untuk menerapkan.\n";
