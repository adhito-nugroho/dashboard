<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model tabel transfer_perjadin (db_anggaran) — berbasis TRANSAKSI.
 *
 * Satu baris per transaksi perjalanan dinas:
 *   - transfer_ke : nama penerima transfer untuk transaksi tsb.
 *   - potongan    : potongan untuk transaksi tsb (input manual admin).
 *
 * Penerimaan (gross) = transaksi.nilai. Penerimaan bersih = nilai - potongan.
 */
class TransferPerjadin
{
    /**
     * Fitur transfer perjalanan dinas mulai berlaku Oktober 2026. Transaksi yang
     * diinput sebelum tanggal ini dianggap sudah punya file konfigurasi sendiri
     * sehingga tidak lagi ditampilkan pada daftar.
     */
    public const FEATURE_START = '2026-10-01';

    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Peta konfigurasi transfer untuk seluruh transaksi.
     *
     * @return array<int, array{transfer_ke:string, potongan:float}>
     */
    public function getConfigMap(): array
    {
        $rows = $this->db->query("
            SELECT transaksi_id, transfer_ke, potongan
            FROM transfer_perjadin
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['transaksi_id']] = [
                'transfer_ke' => (string) $r['transfer_ke'],
                'potongan' => (float) $r['potongan'],
            ];
        }
        return $map;
    }

    /**
     * Daftar transaksi yang sudah dikonfigurasi (transfer_ke terisi atau potongan > 0)
     * untuk satu batch konfigurasi, digabung dengan datanya untuk export.
     *
     * @return array<int, array>
     */
    public function getBatchTransaksi(int $batchId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.tanggal, t.uraian, t.nama_penerima, t.pegawai_nip, t.nilai,
                   t.nomor_surat_tugas, t.surat_tugas_ref_id,
                   c.transfer_ke, c.potongan
            FROM transfer_perjadin c
            JOIN transaksi t ON t.id = c.transaksi_id
            WHERE c.batch_id = :batch_id
              AND (c.transfer_ke <> '' OR c.potongan > 0)
            ORDER BY c.transfer_ke ASC, t.tanggal ASC, t.id ASC
        ");
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Buat batch konfigurasi bernama. Mengembalikan id batch.
     */
    public function createBatch(string $nama, ?int $userId): int
    {
        $stmt = $this->db->prepare("INSERT INTO transfer_perjadin_batch (nama, created_by) VALUES (?, ?)");
        $stmt->execute([$nama, $userId]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Satu batch konfigurasi berdasarkan id.
     *
     * @return array{id:int, nama:string, created_by:int|null, created_at:string}|null
     */
    public function getBatch(int $batchId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, nama, created_by, created_at FROM transfer_perjadin_batch WHERE id = ?");
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Daftar seluruh batch konfigurasi tersimpan beserta ringkasannya.
     *
     * @return array<int, array{id:int, nama:string, created_at:string, jml:int, total_nilai:float, total_potongan:float}>
     */
    public function getBatches(): array
    {
        $rows = $this->db->query("
            SELECT b.id, b.nama, b.created_at,
                   COUNT(tp.id) AS jml,
                   COALESCE(SUM(t.nilai), 0) AS total_nilai,
                   COALESCE(SUM(tp.potongan), 0) AS total_potongan
            FROM transfer_perjadin_batch b
            LEFT JOIN transfer_perjadin tp ON tp.batch_id = b.id
            LEFT JOIN transaksi t ON t.id = tp.transaksi_id
            GROUP BY b.id, b.nama, b.created_at
            ORDER BY b.id DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'nama' => (string) $r['nama'],
                'created_at' => (string) $r['created_at'],
                'jml' => (int) $r['jml'],
                'total_nilai' => (float) $r['total_nilai'],
                'total_potongan' => (float) $r['total_potongan'],
            ];
        }
        return $out;
    }

    /**
     * Simpan massal konfigurasi transfer (per transaksi_id).
     * Baris tanpa transfer_ke dan potongan <= 0 akan dihapus.
     *
     * @param array<int, array{transfer_ke:string, potongan:float}> $rows transaksi_id => config
     * @return int jumlah baris tersimpan
     */
    public function saveBulk(array $rows, ?int $userId, ?int $batchId = null): int
    {
        $this->db->beginTransaction();
        try {
            $upsert = $this->db->prepare("
                INSERT INTO transfer_perjadin (transaksi_id, batch_id, transfer_ke, potongan, created_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    batch_id    = VALUES(batch_id),
                    transfer_ke = VALUES(transfer_ke),
                    potongan    = VALUES(potongan),
                    updated_at  = NOW()
            ");
            $delete = $this->db->prepare("DELETE FROM transfer_perjadin WHERE transaksi_id = ?");

            $saved = 0;
            foreach ($rows as $transaksiId => $cfg) {
                $transaksiId = (int) $transaksiId;
                if ($transaksiId <= 0) {
                    continue;
                }
                $transferKe = trim((string) ($cfg['transfer_ke'] ?? ''));
                $potongan = (float) ($cfg['potongan'] ?? 0);

                if ($transferKe === '' && $potongan <= 0) {
                    $delete->execute([$transaksiId]);
                    continue;
                }

                $upsert->execute([$transaksiId, $batchId, $transferKe, $potongan, $userId]);
                $saved++;
            }

            $this->db->commit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Daftar transaksi perjalanan dinas yang BELUM masuk konfigurasi mana pun
     * (dengan filter opsional). Hanya transaksi berstatus 'diverifikasi' dan
     * diinput sejak FEATURE_START (Oktober 2026) yang ditampilkan; yang lain
     * dianggap sudah punya file konfigurasi. Transaksi yang sudah tersimpan di
     * transfer_perjadin tidak ditampilkan lagi.
     *
     * @return array<int, array>
     */
    public function getTransaksiPerjadin(?int $tahun = null, ?string $inputDari = null, ?string $inputSampai = null, ?int $seksiId = null, string $q = ''): array
    {
        $conditions = [
            "t.jenis_transaksi = 'perjalanan_dinas'",
            'tp.id IS NULL',
            "t.status = 'diverifikasi'",
            'DATE(t.created_at) >= :feature_start',
        ];
        $params = [':feature_start' => self::FEATURE_START];

        if ($tahun !== null) {
            $conditions[] = 'YEAR(t.tanggal) = :tahun';
            $params[':tahun'] = $tahun;
        }
        if ($inputDari !== null) {
            $conditions[] = 'DATE(t.created_at) >= :input_dari';
            $params[':input_dari'] = $inputDari;
        }
        if ($inputSampai !== null) {
            $conditions[] = 'DATE(t.created_at) <= :input_sampai';
            $params[':input_sampai'] = $inputSampai;
        }
        if ($seksiId !== null) {
            $conditions[] = 't.seksi_id = :seksi_id';
            $params[':seksi_id'] = $seksiId;
        }
        if ($q !== '') {
            $conditions[] = '(t.uraian LIKE :kw_a OR t.nama_penerima LIKE :kw_b OR t.nomor_surat_tugas LIKE :kw_c)';
            $params[':kw_a'] = '%' . $q . '%';
            $params[':kw_b'] = '%' . $q . '%';
            $params[':kw_c'] = '%' . $q . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT t.id, t.tanggal, t.uraian, t.nama_penerima, t.pegawai_nip, t.nilai,
                   t.nomor_surat_tugas, t.surat_tugas_ref_id, t.status, t.sumber_dana,
                   t.seksi_id, s.nama_seksi, s.kode_seksi
            FROM transaksi t
            LEFT JOIN seksi s ON s.id = t.seksi_id
            LEFT JOIN transfer_perjadin tp ON tp.transaksi_id = t.id
            {$where}
            ORDER BY t.tanggal DESC, t.id DESC
            LIMIT 2000
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ambil referensi Surat Tugas dari sekumpulan id transaksi.
     *
     * @param int[] $ids
     * @return array<int, array{ref:int, nomor:string}>
     */
    public function getTransaksiStRefs(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT id, surat_tugas_ref_id, nomor_surat_tugas
            FROM transaksi WHERE id IN ($ph)
        ");
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id']] = [
                'ref' => (int) $r['surat_tugas_ref_id'],
                'nomor' => trim((string) $r['nomor_surat_tugas']),
            ];
        }
        return $map;
    }

    /**
     * Cari id Surat Tugas dari nomor suratnya (cocok persis).
     *
     * @param string[] $nomors
     * @return array<string, int> nomor => id
     */
    public function resolveSuratTugasIdsByNomor(array $nomors): array
    {
        $nomors = array_values(array_unique(array_filter(array_map('trim', $nomors), static fn(string $n): bool => $n !== '')));
        if ($nomors === []) {
            return [];
        }
        $pdo = $this->suratTugasConnection();
        if ($pdo === null) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($nomors), '?'));
        try {
            $stmt = $pdo->prepare("SELECT id, nomor_surat FROM surat_tugas WHERE nomor_surat IN ($ph)");
            $stmt->execute($nomors);
            $map = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $map[(string) $r['nomor_surat']] = (int) $r['id'];
            }
            return $map;
        } catch (\Throwable $e) {
            error_log('TransferPerjadin::resolveSuratTugasIdsByNomor error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Daftar nama pegawai peserta untuk sekumpulan id Surat Tugas.
     *
     * @param int[] $stIds
     * @return array<int, string[]> st_id => daftar nama
     */
    public function getPegawaiNamesBySuratTugas(array $stIds): array
    {
        $stIds = array_values(array_unique(array_filter(array_map('intval', $stIds), static fn(int $i): bool => $i > 0)));
        if ($stIds === []) {
            return [];
        }
        $pdo = $this->suratTugasConnection();
        if ($pdo === null) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($stIds), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT pt.id_surat_tugas AS st_id, COALESCE(p.nama, pt.nip) AS nama
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas IN ($ph)
                ORDER BY pt.id_surat_tugas, pt.urutan
            ");
            $stmt->execute($stIds);

            $sets = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $nama = trim((string) $r['nama']);
                if ($nama === '') {
                    continue;
                }
                $sets[(int) $r['st_id']][$nama] = true;
            }

            $out = [];
            foreach ($sets as $st => $set) {
                $list = array_keys($set);
                sort($list, SORT_NATURAL | SORT_FLAG_CASE);
                $out[(int) $st] = $list;
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('TransferPerjadin::getPegawaiNamesBySuratTugas error: ' . $e->getMessage());
            return [];
        }
    }

    private function suratTugasConnection(): ?\PDO
    {
        $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
        if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
            require_once $cfg;
        }
        if (!class_exists('DatabaseSuratTugas')) {
            return null;
        }
        return \DatabaseSuratTugas::getConnection();
    }
}
