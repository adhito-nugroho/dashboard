<?php

namespace App\Models;

use PDO;
use PDOException;

class SubKegiatan {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all sub-activities with activity and program information
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT sk.*, 
                       k.id as kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.id as program_id, p.nama_program, p.kode_program, p.tahun,
                       s.kode_seksi, s.nama_seksi
                FROM sub_kegiatan sk
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                LEFT JOIN seksi s ON sk.seksi_id = s.id
                ORDER BY p.tahun DESC, p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching sub-activities: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch sub-activities');
        }
    }
    
    /**
     * Get sub-activity by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT sk.*, 
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.nama_program, p.kode_program, p.tahun,
                       s.kode_seksi, s.nama_seksi
                FROM sub_kegiatan sk
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                LEFT JOIN seksi s ON sk.seksi_id = s.id
                WHERE sk.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching sub-activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch sub-activity');
        }
    }
    
    /**
     * Get sub-activities by activity ID
     * 
     * @param int $kegiatanId
     * @return array
     */
    public function getByKegiatanId(int $kegiatanId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sub_kegiatan 
                WHERE kegiatan_id = :kegiatan_id 
                ORDER BY kode_sub_kegiatan ASC
            ");
            $stmt->bindParam(':kegiatan_id', $kegiatanId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching sub-activities by activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch sub-activities');
        }
    }
    
    /**
     * Create new sub-activity
     * 
     * @param int $kegiatanId
     * @param string $kodeSubKegiatan
     * @param string $namaSubKegiatan
     * @return int Inserted ID
     */
    public function create(int $kegiatanId, int $seksiId, string $kodeSubKegiatan, string $namaSubKegiatan): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sub_kegiatan (kegiatan_id, seksi_id, kode_sub_kegiatan, nama_sub_kegiatan) 
                VALUES (:kegiatan_id, :seksi_id, :kode_sub_kegiatan, :nama_sub_kegiatan)
            ");
            $stmt->bindParam(':kegiatan_id', $kegiatanId, PDO::PARAM_INT);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_sub_kegiatan', $kodeSubKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':nama_sub_kegiatan', $namaSubKegiatan, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating sub-activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create sub-activity');
        }
    }
    
    /**
     * Pencarian cepat hierarki klasifikasi untuk shortcut form transaksi.
     * $mode 'sub': match nama/kode sub_kegiatan (satu baris per sub).
     * $mode 'rekening': match nama/kode rekening (satu baris per rekening).
     * $seksiId null = global (admin); diisi = hanya milik seksi itu.
     * Selalu prepared statement; wildcard LIKE di-escape.
     *
     * @return array Baris berisi id + kode & nama program/kegiatan/sub (+rekening bila mode rekening)
     */
    public function searchHierarchy(?int $seksiId, string $q, string $mode = 'sub', int $limit = 20): array {
        try {
            $q = trim(mb_substr($q, 0, 100));
            if (mb_strlen($q) < 3) {
                return [];
            }
            $mode = $mode === 'rekening' ? 'rekening' : 'sub';
            $limit = max(1, min(50, $limit));
            // Escape wildcard LIKE agar keyword cari literal
            $like = '%' . addcslashes($q, '\\%_') . '%';

            $seksiFilter = $seksiId !== null ? 'AND sk.seksi_id = :seksi_id' : '';

            if ($mode === 'rekening') {
                $stmt = $this->db->prepare("
                    SELECT r.id AS rekening_id, r.kode_rekening, r.nama_rekening,
                           sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                           k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                           p.id AS program_id, p.kode_program, p.nama_program
                    FROM rekening r
                    INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                    INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                    INNER JOIN program p ON k.program_id = p.id
                    WHERE (r.nama_rekening LIKE :kw_nama ESCAPE '\\\\'
                        OR r.kode_rekening LIKE :kw_kode ESCAPE '\\\\')
                        {$seksiFilter}
                    ORDER BY r.kode_rekening ASC
                    LIMIT {$limit}
                ");
            } else {
                $stmt = $this->db->prepare("
                    SELECT sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                           k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                           p.id AS program_id, p.kode_program, p.nama_program
                    FROM sub_kegiatan sk
                    INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                    INNER JOIN program p ON k.program_id = p.id
                    WHERE (sk.nama_sub_kegiatan LIKE :kw_nama ESCAPE '\\\\'
                        OR sk.kode_sub_kegiatan LIKE :kw_kode ESCAPE '\\\\')
                        {$seksiFilter}
                    ORDER BY sk.kode_sub_kegiatan ASC
                    LIMIT {$limit}
                ");
            }
            $stmt->bindParam(':kw_nama', $like, PDO::PARAM_STR);
            $stmt->bindParam(':kw_kode', $like, PDO::PARAM_STR);
            if ($seksiId !== null) {
                $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error searching hierarchy: ' . $e->getMessage());
            throw new \RuntimeException('Failed to search hierarchy');
        }
    }

    /**
     * Create new sub-activity
     * 
     * @param int $kegiatanId
     * @param int $seksiId
     * @param string $kodeSubKegiatan
     * @param string $namaSubKegiatan
     * @return int Inserted ID
     */
    public function update(int $id, int $kegiatanId, int $seksiId, string $kodeSubKegiatan, string $namaSubKegiatan): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE sub_kegiatan 
                SET kegiatan_id = :kegiatan_id,
                    seksi_id = :seksi_id,
                    kode_sub_kegiatan = :kode_sub_kegiatan, 
                    nama_sub_kegiatan = :nama_sub_kegiatan 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':kegiatan_id', $kegiatanId, PDO::PARAM_INT);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_sub_kegiatan', $kodeSubKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':nama_sub_kegiatan', $namaSubKegiatan, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating sub-activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update sub-activity');
        }
    }
    
    /**
     * Delete sub-activity
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM sub_kegiatan WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting sub-activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete sub-activity');
        }
    }
    
    /**
     * Check if kode_sub_kegiatan exists (for validation)
     * 
     * @param string $kodeSubKegiatan
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool
     */
    public function kodeExists(string $kodeSubKegiatan, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM sub_kegiatan WHERE kode_sub_kegiatan = :kode_sub_kegiatan";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':kode_sub_kegiatan', $kodeSubKegiatan, PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking kode_sub_kegiatan: ' . $e->getMessage());
            return false;
        }
    }
}

