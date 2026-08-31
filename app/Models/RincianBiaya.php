<?php

namespace App\Models;

/**
 * Model untuk tabel rincian_biaya_perjalanan_dinas (header)
 * dan rincian_biaya_perjalanan_dinas_detail (baris komponen).
 *
 * Semua query ke db_anggaran — data pegawai/ST sudah di-cache di kolom header.
 */
class RincianBiaya
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────
    // LIST & GET
    // ──────────────────────────────────────────────────────────

    /**
     * Ambil semua header rincian biaya untuk satu Surat Tugas.
     */
    public function getBySuratTugasId(int $suratTugasId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM rincian_biaya_perjalanan_dinas
            WHERE surat_tugas_id = ?
            ORDER BY pegawai_nama ASC
        ");
        $stmt->execute([$suratTugasId]);
        return $stmt->fetchAll();
    }

    /**
     * Ambil satu header rincian biaya by ID, beserta detail komponen.
     * Mengembalikan ['header' => [...], 'details' => [...]] atau null jika tidak ada.
     */
    public function getByIdWithDetails(int $id): ?array
    {
        $stmtHeader = $this->db->prepare("
            SELECT * FROM rincian_biaya_perjalanan_dinas WHERE id = ?
        ");
        $stmtHeader->execute([$id]);
        $header = $stmtHeader->fetch();
        if (!$header) {
            return null;
        }

        $stmtDetail = $this->db->prepare("
            SELECT * FROM rincian_biaya_perjalanan_dinas_detail
            WHERE rincian_biaya_id = ?
            ORDER BY urutan ASC, id ASC
        ");
        $stmtDetail->execute([$id]);
        $details = $stmtDetail->fetchAll();

        return ['header' => $header, 'details' => $details];
    }

    /**
     * Cek apakah rincian biaya untuk (surat_tugas_id, pegawai_nip) sudah ada.
     * Mengembalikan ID jika ada, null jika belum.
     */
    public function findBySuratTugasDanNip(int $suratTugasId, string $nip): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM rincian_biaya_perjalanan_dinas
            WHERE surat_tugas_id = ? AND pegawai_nip = ?
            LIMIT 1
        ");
        $stmt->execute([$suratTugasId, $nip]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ──────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────

    /**
     * Simpan header rincian biaya baru.
     * Kembalikan ID yang baru dibuat.
     *
     * @param array $details  Array of ['nama_komponen', 'harga_satuan', 'jumlah_hari', 'jumlah', 'keterangan']
     */
    public function create(
        int     $suratTugasId,
        string  $nomorSurat,
        string  $pegawaiNip,
        string  $pegawaiNama,
        ?string $pegawaiPangkat,
        ?string $pegawaiJabatan,
        float   $ditetapkanSejumlah,
        float   $dibayarSemula,
        ?string $tempatTanggal,
        ?int    $createdBy,
        array   $details
    ): int {
        $this->db->beginTransaction();
        try {
            $stmtHeader = $this->db->prepare("
                INSERT INTO rincian_biaya_perjalanan_dinas
                    (surat_tugas_id, nomor_surat, pegawai_nip, pegawai_nama,
                     pegawai_pangkat, pegawai_jabatan,
                     ditetapkan_sejumlah, dibayar_semula,
                     tempat_tanggal, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtHeader->execute([
                $suratTugasId, $nomorSurat, $pegawaiNip, $pegawaiNama,
                $pegawaiPangkat, $pegawaiJabatan,
                $ditetapkanSejumlah, $dibayarSemula,
                $tempatTanggal, $createdBy, $createdBy,
            ]);
            $newId = (int) $this->db->lastInsertId();

            $this->insertDetails($newId, $details);

            $this->db->commit();
            return $newId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE
    // ──────────────────────────────────────────────────────────

    /**
     * Update header + ganti semua baris detail (delete-insert).
     */
    public function update(
        int     $id,
        float   $ditetapkanSejumlah,
        float   $dibayarSemula,
        ?string $tempatTanggal,
        ?int    $updatedBy,
        array   $details
    ): bool {
        $this->db->beginTransaction();
        try {
            $stmtHeader = $this->db->prepare("
                UPDATE rincian_biaya_perjalanan_dinas
                SET ditetapkan_sejumlah = ?,
                    dibayar_semula      = ?,
                    tempat_tanggal      = ?,
                    updated_by          = ?,
                    updated_at          = NOW()
                WHERE id = ?
            ");
            $stmtHeader->execute([
                $ditetapkanSejumlah, $dibayarSemula,
                $tempatTanggal, $updatedBy, $id,
            ]);

            // Hapus detail lama, insert ulang
            $this->db->prepare("DELETE FROM rincian_biaya_perjalanan_dinas_detail WHERE rincian_biaya_id = ?")
                ->execute([$id]);

            $this->insertDetails($id, $details);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────

    /**
     * Hapus header (detail terhapus otomatis via FK CASCADE).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM rincian_biaya_perjalanan_dinas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────

    private function insertDetails(int $rincianBiayaId, array $details): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO rincian_biaya_perjalanan_dinas_detail
                (rincian_biaya_id, nama_komponen, harga_satuan, jumlah_hari, jumlah, keterangan, urutan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($details as $urutan => $d) {
            $hargaSatuan = (float) ($d['harga_satuan'] ?? 0);
            $jumlahHari  = isset($d['jumlah_hari']) && $d['jumlah_hari'] !== '' ? (float) $d['jumlah_hari'] : null;
            $jumlah      = (float) ($d['jumlah'] ?? ($jumlahHari !== null ? $hargaSatuan * $jumlahHari : $hargaSatuan));
            $stmt->execute([
                $rincianBiayaId,
                trim($d['nama_komponen'] ?? ''),
                $hargaSatuan,
                $jumlahHari,
                $jumlah,
                trim($d['keterangan'] ?? '') ?: null,
                (int) $urutan,
            ]);
        }
    }
}
