<?php

namespace App\Models;

use PDO;
use PDOException;

class Pagu {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all budget allocations with related information
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       r.kode_rekening, r.nama_rekening,
                       sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id AS sub_kegiatan_seksi_id,
                       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       pr.id AS program_id, pr.kode_program, pr.nama_program
                FROM pagu p
                INNER JOIN rekening r ON p.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program pr ON k.program_id = pr.id
                ORDER BY p.tahun DESC, pr.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, r.kode_rekening ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching budget allocations: ' . $e->getMessage());
            error_log('SQL Error Code: ' . $e->getCode());
            error_log('Stack trace: ' . $e->getTraceAsString());
            // Temporarily show error for debugging
            throw new \RuntimeException('Failed to fetch budget allocations: ' . $e->getMessage());
        }
    }
    
    /**
     * Get budget allocation by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       r.kode_rekening, r.nama_rekening,
                       sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id AS sub_kegiatan_seksi_id,
                       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       pr.id AS program_id, pr.kode_program, pr.nama_program
                FROM pagu p
                INNER JOIN rekening r ON p.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program pr ON k.program_id = pr.id
                WHERE p.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching budget allocation: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch budget allocation');
        }
    }
    
    /**
     * Create new budget allocation
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @param float $nilaiPagu
     * @return int Inserted ID
     */
    public function create(int $rekeningId, int $tahun, float $nilaiPagu): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO pagu (rekening_id, tahun, nilai_pagu) 
                VALUES (:rekening_id, :tahun, :nilai_pagu)
            ");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->bindParam(':nilai_pagu', $nilaiPagu, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating budget allocation: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create budget allocation');
        }
    }
    
    /**
     * Update budget allocation
     * 
     * @param int $id
     * @param int $rekeningId
     * @param int $tahun
     * @param float $nilaiPagu
     * @return bool
     */
    public function update(int $id, int $rekeningId, int $tahun, float $nilaiPagu): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE pagu 
                SET rekening_id = :rekening_id,
                    tahun = :tahun, 
                    nilai_pagu = :nilai_pagu 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->bindParam(':nilai_pagu', $nilaiPagu, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating budget allocation: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update budget allocation');
        }
    }
    
    /**
     * Delete budget allocation
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM pagu WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting budget allocation: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete budget allocation');
        }
    }
    
    /**
     * Check if pagu exists for rekening and tahun (for validation)
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool
     */
    public function exists(int $rekeningId, int $tahun, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM pagu WHERE rekening_id = :rekening_id AND tahun = :tahun";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking pagu existence: ' . $e->getMessage());
            return false;
        }
    }
}

