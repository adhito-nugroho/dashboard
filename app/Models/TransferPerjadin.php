<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Model tabel transfer_perjadin (db_anggaran).
 *
 * Satu baris per (surat_tugas_id, pegawai_nip):
 * - is_transfer_ke = 1 untuk pegawai yang menerima transfer pada ST tsb.
 * - potongan diisi manual oleh admin.
 *
 * Juga menyediakan akses transaksi perjalanan dinas (sumber penerimaan)
 * dari tabel transaksi.
 */
class TransferPerjadin
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ambil konfigurasi transfer untuk satu Surat Tugas, terindeks pegawai_nip.
     *
     * @return array<string, array> map: nip => [is_transfer_ke, potongan, ...]
     */
    public function getBySuratTugasId(int $suratTugasId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM transfer_perjadin
            WHERE surat_tugas_id = ?
            ORDER BY pegawai_nama ASC
        ");
        $stmt->execute([$suratTugasId]);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[trim((string) $row['pegawai_nip'])] = $row;
        }
        return $map;
    }

    /**
     * Daftar surat_tugas_id yang sudah punya konfigurasi transfer.
     *
     * @return int[]
     */
    public function getConfiguredSuratTugasIds(): array
    {
        $rows = $this->db->query("SELECT DISTINCT surat_tugas_id FROM transfer_perjadin")
            ->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /**
     * Daftar (surat_tugas_id => jumlah baris) untuk badge status di halaman index.
     *
     * @return array<int, int>
     */
    public function getConfiguredCounts(): array
    {
        $rows = $this->db->query("
            SELECT surat_tugas_id, COUNT(*) AS jml
            FROM transfer_perjadin
            GROUP BY surat_tugas_id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['surat_tugas_id']] = (int) $row['jml'];
        }
        return $map;
    }

    /**
     * Simpan (upsert) konfigurasi transfer untuk satu Surat Tugas.
     * Hanya satu pegawai yang boleh ditandai sebagai penerima transfer.
     * Baris yang tidak lagi dikirim akan dihapus.
     *
     * @param array<int, array{nip:string, nama:string, potongan:float, is_transfer_ke:bool}> $rows
     * @return int jumlah baris tersimpan
     */
    public function saveForSuratTugas(int $suratTugasId, string $nomorSurat, array $rows, ?int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $transferKeNip = null;
            foreach ($rows as $r) {
                if (!empty($r['is_transfer_ke'])) {
                    $transferKeNip = $r['nip'];
                    break;
                }
            }

            $upsert = $this->db->prepare("
                INSERT INTO transfer_perjadin
                    (surat_tugas_id, nomor_surat, pegawai_nip, pegawai_nama, is_transfer_ke, potongan, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nomor_surat   = VALUES(nomor_surat),
                    pegawai_nama  = VALUES(pegawai_nama),
                    is_transfer_ke = VALUES(is_transfer_ke),
                    potongan      = VALUES(potongan),
                    updated_at    = NOW()
            ");

            $savedNips = [];
            foreach ($rows as $r) {
                $nip = (string) $r['nip'];
                $isKe = ($transferKeNip !== null && $nip === $transferKeNip) ? 1 : 0;
                $upsert->execute([
                    $suratTugasId,
                    $nomorSurat,
                    $nip,
                    (string) $r['nama'],
                    $isKe,
                    (float) ($r['potongan'] ?? 0),
                    $userId,
                ]);
                $savedNips[] = $nip;
            }

            if ($savedNips === []) {
                $this->db->prepare("DELETE FROM transfer_perjadin WHERE surat_tugas_id = ?")
                    ->execute([$suratTugasId]);
            } else {
                $placeholders = implode(',', array_fill(0, count($savedNips), '?'));
                $this->db->prepare("
                    DELETE FROM transfer_perjadin
                    WHERE surat_tugas_id = ? AND pegawai_nip NOT IN ($placeholders)
                ")->execute(array_merge([$suratTugasId], $savedNips));
            }

            $this->db->commit();
            return count($savedNips);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Hapus seluruh konfigurasi transfer untuk satu Surat Tugas.
     */
    public function deleteBySuratTugasId(int $suratTugasId): int
    {
        $stmt = $this->db->prepare("DELETE FROM transfer_perjadin WHERE surat_tugas_id = ?");
        $stmt->execute([$suratTugasId]);
        return $stmt->rowCount();
    }

    /**
     * Transaksi perjalanan dinas untuk satu Surat Tugas (sumber penerimaan).
     * Cocok via surat_tugas_ref_id atau nomor_surat_tugas.
     *
     * @return array<int, array>
     */
    public function getTransaksiPerjadinBySuratTugas(int $suratTugasId, string $nomorSurat): array
    {
        $stmt = $this->db->prepare("
            SELECT id, tanggal, uraian, nama_penerima, pegawai_nip, nilai,
                   nomor_surat_tugas, surat_tugas_ref_id
            FROM transaksi
            WHERE jenis_transaksi = 'perjalanan_dinas'
              AND (surat_tugas_ref_id = :st OR nomor_surat_tugas = :no)
            ORDER BY id ASC
        ");
        $stmt->execute([':st' => $suratTugasId, ':no' => $nomorSurat]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
