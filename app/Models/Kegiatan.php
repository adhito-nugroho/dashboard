<?php

namespace App\Models;

use PDO;
use PDOException;

class Kegiatan {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all activities with program information
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, p.id as program_id, p.nama_program, p.kode_program, p.tahun 
                FROM kegiatan k
                INNER JOIN program p ON k.program_id = p.id
                ORDER BY p.tahun DESC, p.kode_program ASC, k.kode_kegiatan ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching activities: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch activities');
        }
    }
    
    /**
     * Get activity by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, p.nama_program, p.kode_program, p.tahun 
                FROM kegiatan k
                INNER JOIN program p ON k.program_id = p.id
                WHERE k.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch activity');
        }
    }
    
    /**
     * Get activities by program ID
     * 
     * @param int $programId
     * @return array
     */
    public function getByProgramId(int $programId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM kegiatan 
                WHERE program_id = :program_id 
                ORDER BY kode_kegiatan ASC
            ");
            $stmt->bindParam(':program_id', $programId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching activities by program: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch activities');
        }
    }
    
    /**
     * Create new activity
     * 
     * @param int $programId
     * @param string $kodeKegiatan
     * @param string $namaKegiatan
     * @return int Inserted ID
     */
    public function create(int $programId, string $kodeKegiatan, string $namaKegiatan): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO kegiatan (program_id, kode_kegiatan, nama_kegiatan) 
                VALUES (:program_id, :kode_kegiatan, :nama_kegiatan)
            ");
            $stmt->bindParam(':program_id', $programId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_kegiatan', $kodeKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':nama_kegiatan', $namaKegiatan, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create activity');
        }
    }
    
    /**
     * Update activity
     * 
     * @param int $id
     * @param int $programId
     * @param string $kodeKegiatan
     * @param string $namaKegiatan
     * @return bool
     */
    public function update(int $id, int $programId, string $kodeKegiatan, string $namaKegiatan): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE kegiatan 
                SET program_id = :program_id,
                    kode_kegiatan = :kode_kegiatan, 
                    nama_kegiatan = :nama_kegiatan 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':program_id', $programId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_kegiatan', $kodeKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':nama_kegiatan', $namaKegiatan, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update activity');
        }
    }
    
    /**
     * Delete activity
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM kegiatan WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete activity');
        }
    }
    
    /**
     * Check if kode_kegiatan exists (for validation)
     * 
     * @param string $kodeKegiatan
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool
     */
    public function kodeExists(string $kodeKegiatan, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM kegiatan WHERE kode_kegiatan = :kode_kegiatan";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':kode_kegiatan', $kodeKegiatan, PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking kode_kegiatan: ' . $e->getMessage());
            return false;
        }
    }
}

