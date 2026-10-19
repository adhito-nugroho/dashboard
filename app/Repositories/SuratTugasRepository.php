<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

/**
 * Read-only repository untuk database eksternal `db_surat_tugas`
 * (aplikasi Surat Tugas).
 *
 * Prinsip:
 * - Hanya menjalankan SELECT (tidak pernah INSERT/UPDATE/DELETE).
 * - Degradasi anggun: bila koneksi tidak tersedia, semua method mengembalikan
 *   null / [] sehingga halaman pemanggil tetap dapat tampil.
 * - Koneksi di-resolve lazily dan dapat disuntik (PDO) untuk pengujian.
 */
class SuratTugasRepository
{
    /** Kolom header ST standar selain kota_tujuan (yang mungkin belum ada). */
    private const HEADER_COLUMNS_BASE = 'id, nomor_surat, tanggal_surat, untuk,
            tanggal_mulai, tanggal_selesai, dasar_surat';

    /**
     * Status keberadaan kolom kota_tujuan, di-cache per proses.
     * null = belum diperiksa. Aplikasi Surat Tugas versi lama belum punya
     * kolom ini; bila tidak ada, semua method tetap berjalan dan kota_tujuan
     * dianggap kosong (NULL).
     */
    private static ?bool $schemaHasKotaTujuan = null;

    private ?PDO $pdo;
    private bool $resolved;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->resolved = $pdo !== null;
    }

    /**
     * Ambil koneksi db_surat_tugas (lazy). Return null bila tidak tersedia.
     */
    public function connection(): ?PDO
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
            if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
                require_once $cfg;
            }
            $this->pdo = class_exists('DatabaseSuratTugas')
                ? \DatabaseSuratTugas::getConnection()
                : null;
        }

        return $this->pdo;
    }

    public function isAvailable(): bool
    {
        return $this->connection() !== null;
    }

    /**
     * Apakah tabel surat_tugas memiliki kolom `kota_tujuan`?
     * Di-cache statis agar hanya satu query SHOW COLUMNS per proses.
     */
    public function hasKotaTujuanColumn(): bool
    {
        if (self::$schemaHasKotaTujuan !== null) {
            return self::$schemaHasKotaTujuan;
        }

        $pdo = $this->connection();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM surat_tugas LIKE 'kota_tujuan'");
            self::$schemaHasKotaTujuan = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::hasKotaTujuanColumn error: ' . $e->getMessage());
            self::$schemaHasKotaTujuan = false;
        }

        return self::$schemaHasKotaTujuan;
    }

    /** Ekspresi kolom kota_tujuan (NULL bila kolom belum ada di schema). */
    private function kotaTujuanExpr(PDO $pdo): string
    {
        return $this->hasKotaTujuanColumn() ? 'kota_tujuan' : 'NULL AS kota_tujuan';
    }

    /** Daftar kolom header ST sesuai schema yang tersedia. */
    private function headerColumns(PDO $pdo): string
    {
        return self::HEADER_COLUMNS_BASE . ', ' . $this->kotaTujuanExpr($pdo);
    }

    /**
     * Daftar Surat Tugas dengan filter opsional.
     *
     * @param array{q?:string,bulan?:int|null,tahun?:int|null,limit?:int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return [];
        }

        $q     = trim((string) ($filters['q'] ?? ''));
        $bulan = isset($filters['bulan']) && $filters['bulan'] !== '' ? (int) $filters['bulan'] : null;
        $tahun = isset($filters['tahun']) && $filters['tahun'] !== '' ? (int) $filters['tahun'] : (int) date('Y');
        $limit = (int) ($filters['limit'] ?? 100);

        $conditions = [];
        $params     = [];

        if ($q !== '') {
            $conditions[] = '(nomor_surat LIKE :kw_no OR untuk LIKE :kw_untuk)';
            $kw = '%' . $q . '%';
            $params[':kw_no'] = $kw;
            $params[':kw_untuk'] = $kw;
        }
        if ($bulan !== null) {
            $conditions[] = 'MONTH(tanggal_mulai) = :bulan';
            $params[':bulan'] = $bulan;
        }
        $conditions[] = 'YEAR(tanggal_mulai) = :tahun';
        $params[':tahun'] = $tahun;

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        try {
            $stmt = $pdo->prepare("
                SELECT " . $this->headerColumns($pdo) . "
                FROM surat_tugas
                {$where}
                ORDER BY tanggal_mulai DESC, id DESC
                LIMIT {$limit}
            ");
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::list error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ambil satu header Surat Tugas by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $pdo = $this->connection();
        if ($pdo === null || $id <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT ' . $this->headerColumns($pdo) . ' FROM surat_tugas WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::find error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Daftar pegawai dalam satu Surat Tugas (untuk halaman detail).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pegawaiList(int $suratTugasId): array
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    pt.nip AS pt_nip,
                    COALESCE(p.nip, pt.nip)             AS nip,
                    COALESCE(p.nama, pt.nip, 'Pegawai') AS nama,
                    p.pangkat,
                    p.jabatan,
                    pt.urutan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas = ?
                ORDER BY pt.urutan ASC, p.nama ASC
            ");
            $stmt->execute([$suratTugasId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::pegawaiList error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ambil satu pegawai dalam Surat Tugas berdasarkan NIP (toleran spasi).
     *
     * @return array<string, mixed>|null
     */
    public function findPegawai(int $suratTugasId, string $nip): ?array
    {
        $pdo = $this->connection();
        if ($pdo === null || $nip === '') {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(p.nip, pt.nip)             AS nip,
                    COALESCE(p.nama, pt.nip, 'Pegawai') AS nama,
                    p.pangkat,
                    p.jabatan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas = ?
                  AND (pt.nip = ? OR REPLACE(pt.nip, ' ', '') = REPLACE(?, ' ', ''))
                LIMIT 1
            ");
            $stmt->execute([$suratTugasId, $nip, $nip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::findPegawai error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Peta id Surat Tugas => apakah "Kota Tujuan" kosong (batch, tanpa N+1).
     * Return [] bila koneksi/kolom tidak tersedia (aplikasi ST versi lama).
     *
     * @param array<int, int|string> $ids
     * @return array<int, bool>
     */
    public function kotaTujuanKosongMap(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        if (!$clean) {
            return [];
        }

        $pdo = $this->connection();
        if ($pdo === null) {
            return [];
        }

        // Schema lama tanpa kolom kota_tujuan: anggap semua kota tujuan kosong
        // agar UI tetap memberi tahu (SPD tidak dicetak dengan tujuan salah).
        if (!$this->hasKotaTujuanColumn()) {
            return array_fill_keys(array_values($clean), true);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($clean), '?'));
            $stmt = $pdo->prepare("
                SELECT id, kota_tujuan
                FROM surat_tugas
                WHERE id IN ({$placeholders})
            ");
            $stmt->execute(array_values($clean));

            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['id']] = trim((string) ($row['kota_tujuan'] ?? '')) === '';
            }
            return $map;
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::kotaTujuanKosongMap error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Peta [id transaksi => kota_tujuan kosong?] untuk baris transaksi
     * perjalanan dinas. Menyaring hanya jenis_transaksi perjalanan dinas yang
     * punya surat_tugas_ref_id, lalu menanyakan kota_tujuan secara batch.
     *
     * @param array<int, array<string, mixed>> $transaksis
     * @return array<int, bool>
     */
    public function kotaTujuanKosongMapForTransaksi(array $transaksis): array
    {
        $stIds = [];
        foreach ($transaksis as $row) {
            if (($row['jenis_transaksi'] ?? '') === 'perjalanan_dinas' && !empty($row['surat_tugas_ref_id'])) {
                $stIds[] = (int) $row['surat_tugas_ref_id'];
            }
        }
        if (!$stIds) {
            return [];
        }

        $stKosong = $this->kotaTujuanKosongMap($stIds);

        $map = [];
        foreach ($transaksis as $row) {
            if (($row['jenis_transaksi'] ?? '') !== 'perjalanan_dinas') {
                continue;
            }
            $refId = (int) ($row['surat_tugas_ref_id'] ?? 0);
            if ($refId > 0 && array_key_exists($refId, $stKosong)) {
                $map[(int) $row['id']] = $stKosong[$refId];
            }
        }
        return $map;
    }

    /**
     * Cari ID Surat Tugas dari nomornya (cocok persis dulu, lalu LIKE).
     */
    public function resolveIdByNomor(string $nomor): ?int
    {
        $nomor = trim($nomor);
        if ($nomor === '') {
            return null;
        }

        $pdo = $this->connection();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT id FROM surat_tugas WHERE nomor_surat = ? LIMIT 1');
            $stmt->execute([$nomor]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }

            $stmt = $pdo->prepare('SELECT id FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1');
            $stmt->execute(['%' . $nomor . '%']);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (Throwable $e) {
            error_log('SuratTugasRepository::resolveIdByNomor error: ' . $e->getMessage());
            return null;
        }
    }
}
