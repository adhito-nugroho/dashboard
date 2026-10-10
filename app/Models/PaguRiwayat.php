<?php

namespace App\Models;

use PDO;
use PDOException;

class PaguRiwayat
{
    private PDO $db;

    public const JENIS_VALID = ['APBD', 'PAPBD', 'KOREKSI'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function normalizeJenis(mixed $value): string
    {
        $v = strtoupper(trim((string) $value));
        return in_array($v, self::JENIS_VALID, true) ? $v : 'PAPBD';
    }

    /**
     * Catat satu perubahan pagu. Dipanggil SETELAH pagu berhasil di-update.
     */
    public function log(
        int $paguId,
        int $rekeningId,
        int $tahun,
        float $nilaiSebelum,
        float $nilaiSesudah,
        string $jenis = 'PAPBD',
        ?string $keterangan = null,
        ?int $createdBy = null
    ): int {
        $jenis = self::normalizeJenis($jenis);
        $selisih = $nilaiSesudah - $nilaiSebelum;
        $keterangan = $keterangan !== null ? trim($keterangan) : null;
        if ($keterangan === '') {
            $keterangan = null;
        }
        try {
            $stmt = $this->db->prepare("
                INSERT INTO pagu_riwayat
                    (pagu_id, rekening_id, tahun, nilai_sebelum, nilai_sesudah, selisih, jenis, keterangan, created_by)
                VALUES
                    (:pagu_id, :rekening_id, :tahun, :nilai_sebelum, :nilai_sesudah, :selisih, :jenis, :keterangan, :created_by)
            ");
            $stmt->bindValue(':pagu_id', $paguId, PDO::PARAM_INT);
            $stmt->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->bindValue(':nilai_sebelum', (string) $nilaiSebelum, PDO::PARAM_STR);
            $stmt->bindValue(':nilai_sesudah', (string) $nilaiSesudah, PDO::PARAM_STR);
            $stmt->bindValue(':selisih', (string) $selisih, PDO::PARAM_STR);
            $stmt->bindValue(':jenis', $jenis, PDO::PARAM_STR);
            $stmt->bindValue(':keterangan', $keterangan, $keterangan === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error logging pagu riwayat: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mencatat riwayat perubahan pagu');
        }
    }

    /**
     * Riwayat satu pagu (terbaru dulu), join username pelaku bila tabel users ada.
     */
    public function getByPaguId(int $paguId): array
    {
        try {
            // Cek apakah kolom users.username tersedia (skema users bisa berbeda antar env)
            $hasUsers = false;
            try {
                $chk = $this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
                $hasUsers = $chk && (int) $chk->fetchColumn() > 0;
            } catch (\Throwable) {
                $hasUsers = false;
            }
            if ($hasUsers) {
                $stmt = $this->db->prepare("
                    SELECT pr.*, u.username AS diubah_oleh
                    FROM pagu_riwayat pr
                    LEFT JOIN users u ON u.id = pr.created_by
                    WHERE pr.pagu_id = :pagu_id
                    ORDER BY pr.id DESC
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT pr.*, NULL AS diubah_oleh
                    FROM pagu_riwayat pr
                    WHERE pr.pagu_id = :pagu_id
                    ORDER BY pr.id DESC
                ");
            }
            $stmt->bindValue(':pagu_id', $paguId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching pagu riwayat: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Nilai APBD awal satu pagu = nilai_sebelum log pertama.
     * Null bila belum pernah berubah (panggil fallback ke nilai saat ini).
     */
    public function getNilaiAwal(int $paguId): ?float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT nilai_sebelum FROM pagu_riwayat
                WHERE pagu_id = :pagu_id ORDER BY id ASC LIMIT 1
            ");
            $stmt->bindValue(':pagu_id', $paguId, PDO::PARAM_INT);
            $stmt->execute();
            $val = $stmt->fetchColumn();
            return $val === false ? null : (float) $val;
        } catch (PDOException $e) {
            error_log('Error fetching nilai awal pagu: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Peta pagu_id => nilai_awal untuk satu tahun (1 query, anti N+1).
     */
    public function getNilaiAwalMapByTahun(int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.pagu_id, pr.nilai_sebelum
                FROM pagu_riwayat pr
                INNER JOIN (
                    SELECT pagu_id, MIN(id) AS first_id
                    FROM pagu_riwayat WHERE tahun = :tahun GROUP BY pagu_id
                ) f ON f.first_id = pr.id
            ");
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['pagu_id']] = (float) $row['nilai_sebelum'];
            }
            return $map;
        } catch (PDOException $e) {
            error_log('Error fetching nilai awal map: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ringkasan perubahan satu tahun untuk kartu laporan selisih.
     */
    public function getRingkasanByTahun(int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS jumlah_perubahan,
                       COALESCE(SUM(CASE WHEN selisih > 0 THEN selisih ELSE 0 END), 0) AS total_tambah,
                       COALESCE(SUM(CASE WHEN selisih < 0 THEN selisih ELSE 0 END), 0) AS total_kurang,
                       COALESCE(SUM(selisih), 0) AS total_selisih,
                       COUNT(DISTINCT pagu_id) AS jumlah_rekening_berubah
                FROM pagu_riwayat WHERE tahun = :tahun
            ");
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'jumlah_perubahan' => (int) ($row['jumlah_perubahan'] ?? 0),
                'total_tambah' => (float) ($row['total_tambah'] ?? 0),
                'total_kurang' => (float) ($row['total_kurang'] ?? 0),
                'total_selisih' => (float) ($row['total_selisih'] ?? 0),
                'jumlah_rekening_berubah' => (int) ($row['jumlah_rekening_berubah'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('Error fetching ringkasan riwayat: ' . $e->getMessage());
            return ['jumlah_perubahan' => 0, 'total_tambah' => 0.0, 'total_kurang' => 0.0, 'total_selisih' => 0.0, 'jumlah_rekening_berubah' => 0];
        }
    }
}
