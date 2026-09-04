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
     * Ambil rincian biaya (header + detail) yang terhubung ke transaksi_id tertentu.
     * Jika tidak ditemukan langsung by transaksi_id, lakukan fallback pencarian via relasi transaksi.
     */
    public function getByTransaksiId(int $transaksiId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM rincian_biaya_perjalanan_dinas
            WHERE transaksi_id = ? LIMIT 1
        ");
        $stmt->execute([$transaksiId]);
        $header = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$header) {
            // Ambil data transaksi terkait untuk fallback lookup
            $stmtT = $this->db->prepare("
                SELECT id, jenis_transaksi, surat_tugas_ref_id, nomor_surat_tugas, pegawai_nip, nama_penerima
                FROM transaksi WHERE id = ? LIMIT 1
            ");
            $stmtT->execute([$transaksiId]);
            $trx = $stmtT->fetch(\PDO::FETCH_ASSOC);
            if ($trx) {
                return $this->findForTransaksi($trx);
            }
            return null;
        }

        $stmtD = $this->db->prepare("
            SELECT * FROM rincian_biaya_perjalanan_dinas_detail
            WHERE rincian_biaya_id = ? ORDER BY urutan ASC, id ASC
        ");
        $stmtD->execute([(int) $header['id']]);
        return ['header' => $header, 'details' => $stmtD->fetchAll(\PDO::FETCH_ASSOC)];
    }

    /**
     * Cari rincian biaya (header + detail) untuk suatu record transaksi.
     * 1. Cek langsung via transaksi_id
     * 2. Fallback via surat_tugas_ref_id + pegawai_nip / nama_penerima
     * 3. Fallback via nomor_surat_tugas + pegawai_nip / nama_penerima
     * Jika ditemukan tapi transaksi_id masih null/0, otomatis tautkan transaksi_id.
     */
    public function findForTransaksi(array $transaksi): ?array
    {
        $transaksiId = (int) ($transaksi['id'] ?? 0);
        if ($transaksiId > 0) {
            $stmt = $this->db->prepare("
                SELECT * FROM rincian_biaya_perjalanan_dinas
                WHERE transaksi_id = ? LIMIT 1
            ");
            $stmt->execute([$transaksiId]);
            $header = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($header) {
                $stmtD = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas_detail
                    WHERE rincian_biaya_id = ? ORDER BY urutan ASC, id ASC
                ");
                $stmtD->execute([(int) $header['id']]);
                return ['header' => $header, 'details' => $stmtD->fetchAll(\PDO::FETCH_ASSOC)];
            }
        }

        $stRefId = !empty($transaksi['surat_tugas_ref_id']) ? (int) $transaksi['surat_tugas_ref_id'] : 0;
        $nip     = trim((string) ($transaksi['pegawai_nip'] ?? ''));
        $nama    = trim((string) ($transaksi['nama_penerima'] ?? ''));
        $noST    = trim((string) ($transaksi['nomor_surat_tugas'] ?? ''));

        $header = null;

        // Fallback 1: via surat_tugas_ref_id
        if ($stRefId > 0) {
            if ($nip !== '' && $nip !== '-') {
                $stmt = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas
                    WHERE surat_tugas_id = ? AND pegawai_nip = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$stRefId, $nip]);
                $header = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            if (!$header && $nama !== '') {
                $stmt = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas
                    WHERE surat_tugas_id = ? AND (pegawai_nama = ? OR pegawai_nama LIKE ?)
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$stRefId, $nama, '%' . $nama . '%']);
                $header = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            if (!$header) {
                $stmt = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas
                    WHERE surat_tugas_id = ?
                    ORDER BY id DESC
                ");
                $stmt->execute([$stRefId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (count($rows) === 1) {
                    $header = $rows[0];
                }
            }
        }

        // Fallback 2: via nomor_surat_tugas
        if (!$header && $noST !== '') {
            if ($nip !== '' && $nip !== '-') {
                $stmt = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas
                    WHERE (nomor_surat = ? OR nomor_surat LIKE ?) AND pegawai_nip = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$noST, '%' . $noST . '%', $nip]);
                $header = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            if (!$header && $nama !== '') {
                $stmt = $this->db->prepare("
                    SELECT * FROM rincian_biaya_perjalanan_dinas
                    WHERE (nomor_surat = ? OR nomor_surat LIKE ?) AND (pegawai_nama = ? OR pegawai_nama LIKE ?)
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$noST, '%' . $noST . '%', $nama, '%' . $nama . '%']);
                $header = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
        }

        if (!$header) {
            return null;
        }

        // Otomatis tautkan transaksi_id jika masih kosong
        if ($transaksiId > 0 && empty($header['transaksi_id'])) {
            try {
                $stmtLink = $this->db->prepare("
                    UPDATE rincian_biaya_perjalanan_dinas
                    SET transaksi_id = ?
                    WHERE id = ? AND (transaksi_id IS NULL OR transaksi_id = 0)
                ");
                $stmtLink->execute([$transaksiId, (int) $header['id']]);
                $header['transaksi_id'] = $transaksiId;
            } catch (\Throwable $e) {
                // non-blocking
            }
        }

        $stmtD = $this->db->prepare("
            SELECT * FROM rincian_biaya_perjalanan_dinas_detail
            WHERE rincian_biaya_id = ? ORDER BY urutan ASC, id ASC
        ");
        $stmtD->execute([(int) $header['id']]);
        return ['header' => $header, 'details' => $stmtD->fetchAll(\PDO::FETCH_ASSOC)];
    }

    /**
     * Simpan rincian biaya yang terhubung langsung ke satu transaksi (diinput seksi).
     * Jika sudah ada record untuk transaksi_id ini atau (surat_tugas_id + pegawai_nip), perbarui lalu insert ulang detail.
     */
    public function upsertDariTransaksi(
        int     $transaksiId,
        int     $suratTugasId,
        string  $nomorSurat,
        string  $pegawaiNip,
        string  $pegawaiNama,
        ?string $pegawaiPangkat,
        ?string $pegawaiJabatan,
        float   $ditetapkanSejumlah,
        float   $dibayarSemula,
        ?string $tempatTanggal,
        ?int    $userId,
        array   $details
    ): int {
        $this->db->beginTransaction();
        try {
            // Cek sudah ada berdasarkan transaksi_id ATAU (surat_tugas_id + pegawai_nip)?
            $existing = $this->db->prepare("
                SELECT id FROM rincian_biaya_perjalanan_dinas
                WHERE transaksi_id = ?
                   OR (? > 0 AND surat_tugas_id = ? AND ? != '' AND ? != '-' AND pegawai_nip = ?)
                LIMIT 1
            ");
            $existing->execute([$transaksiId, $suratTugasId, $suratTugasId, $pegawaiNip, $pegawaiNip, $pegawaiNip]);
            $existingId = $existing->fetchColumn();

            if ($existingId) {
                // Update header + tautkan transaksi_id + hapus-insert detail
                $this->db->prepare("
                    UPDATE rincian_biaya_perjalanan_dinas
                    SET transaksi_id = ?, surat_tugas_id = ?, nomor_surat = ?, pegawai_nip = ?, pegawai_nama = ?,
                        pegawai_pangkat = ?, pegawai_jabatan = ?,
                        ditetapkan_sejumlah = ?, dibayar_semula = ?,
                        tempat_tanggal = ?, updated_by = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $transaksiId,
                    $suratTugasId, $nomorSurat, $pegawaiNip, $pegawaiNama,
                    $pegawaiPangkat, $pegawaiJabatan,
                    $ditetapkanSejumlah, $dibayarSemula,
                    $tempatTanggal, $userId, $existingId,
                ]);
                $this->db->prepare(
                    "DELETE FROM rincian_biaya_perjalanan_dinas_detail WHERE rincian_biaya_id = ?"
                )->execute([$existingId]);
                $this->insertDetails($existingId, $details);
                $this->db->commit();
                return (int) $existingId;
            }

            // Insert baru
            $this->db->prepare("
                INSERT INTO rincian_biaya_perjalanan_dinas
                    (transaksi_id, surat_tugas_id, nomor_surat, pegawai_nip, pegawai_nama,
                     pegawai_pangkat, pegawai_jabatan, ditetapkan_sejumlah, dibayar_semula,
                     tempat_tanggal, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $transaksiId, $suratTugasId, $nomorSurat, $pegawaiNip, $pegawaiNama,
                $pegawaiPangkat, $pegawaiJabatan, $ditetapkanSejumlah, $dibayarSemula,
                $tempatTanggal, $userId, $userId,
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
