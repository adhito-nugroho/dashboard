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
     * Daftar transaksi yang sudah dikonfigurasi (transfer_ke terisi atau potongan > 0),
     * digabung dengan datanya untuk export.
     *
     * @return array<int, array>
     */
    public function getConfiguredTransaksi(): array
    {
        return $this->db->query("
            SELECT t.id, t.tanggal, t.uraian, t.nama_penerima, t.pegawai_nip, t.nilai,
                   t.nomor_surat_tugas, t.surat_tugas_ref_id,
                   c.transfer_ke, c.potongan
            FROM transfer_perjadin c
            JOIN transaksi t ON t.id = c.transaksi_id
            WHERE (c.transfer_ke <> '' OR c.potongan > 0)
            ORDER BY c.transfer_ke ASC, t.tanggal ASC, t.id ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Simpan massal konfigurasi transfer (per transaksi_id).
     * Baris tanpa transfer_ke dan potongan <= 0 akan dihapus.
     *
     * @param array<int, array{transfer_ke:string, potongan:float}> $rows transaksi_id => config
     * @return int jumlah baris tersimpan
     */
    public function saveBulk(array $rows, ?int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $upsert = $this->db->prepare("
                INSERT INTO transfer_perjadin (transaksi_id, transfer_ke, potongan, created_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
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

                $upsert->execute([$transaksiId, $transferKe, $potongan, $userId]);
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
     * Daftar transaksi perjalanan dinas untuk ditampilkan (dengan filter opsional).
     *
     * @return array<int, array>
     */
    public function getTransaksiPerjadin(?int $tahun = null, ?int $bulan = null, ?int $seksiId = null, string $q = ''): array
    {
        $conditions = ["t.jenis_transaksi = 'perjalanan_dinas'"];
        $params = [];

        if ($tahun !== null) {
            $conditions[] = 'YEAR(t.tanggal) = :tahun';
            $params[':tahun'] = $tahun;
        }
        if ($bulan !== null) {
            $conditions[] = 'MONTH(t.tanggal) = :bulan';
            $params[':bulan'] = $bulan;
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
     * Daftar nama untuk saran "Transfer Ke": gabungan pegawai (db_surat_tugas)
     * dan nama_penerima yang pernah dipakai pada transaksi perjalanan dinas.
     *
     * @return string[]
     */
    public function getTransferKeSuggestions(): array
    {
        $names = [];

        $rows = $this->db->query("
            SELECT DISTINCT nama_penerima
            FROM transaksi
            WHERE jenis_transaksi = 'perjalanan_dinas'
              AND nama_penerima IS NOT NULL AND nama_penerima <> ''
        ")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($rows as $n) {
            $names[trim((string) $n)] = true;
        }

        $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
        if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
            require_once $cfg;
        }
        if (class_exists('DatabaseSuratTugas')) {
            $pdo = \DatabaseSuratTugas::getConnection();
            if ($pdo !== null) {
                try {
                    $rows = $pdo->query("SELECT DISTINCT nama FROM pegawai WHERE nama IS NOT NULL AND nama <> ''")
                        ->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($rows as $n) {
                        $names[trim((string) $n)] = true;
                    }
                } catch (\Throwable $e) {
                    // abaikan; saran saja
                }
            }
        }

        $out = array_keys($names);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
