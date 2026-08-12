<?php

namespace App\Models;

use PDO;
use PDOException;

class Seksi {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all sections
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM seksi ORDER BY kode_seksi ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching sections: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch sections');
        }
    }
    
    /**
     * Get section by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM seksi WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching section: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch section');
        }
    }
    
    /**
     * Create new section
     * 
     * @param string $kodeSeksi
     * @param string $namaSeksi
     * @param int $tahun
     * @return int Inserted ID
     */
    public function create(string $kodeSeksi, string $namaSeksi): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO seksi (kode_seksi, nama_seksi) 
                VALUES (:kode_seksi, :nama_seksi)
            ");
            $stmt->bindParam(':kode_seksi', $kodeSeksi, PDO::PARAM_STR);
            $stmt->bindParam(':nama_seksi', $namaSeksi, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating section: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create section');
        }
    }
    
    /**
     * Update section
     * 
     * @param int $id
     * @param string $kodeSeksi
     * @param string $namaSeksi
     * @param int $tahun
     * @return bool
     */
    public function update(int $id, string $kodeSeksi, string $namaSeksi): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE seksi 
                SET kode_seksi = :kode_seksi, 
                    nama_seksi = :nama_seksi
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':kode_seksi', $kodeSeksi, PDO::PARAM_STR);
            $stmt->bindParam(':nama_seksi', $namaSeksi, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating section: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update section');
        }
    }
    
    /**
     * Delete section
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM seksi WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting section: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete section');
        }
    }
    
    /**
     * Check if kode_seksi exists (for validation)
     * 
     * @param string $kodeSeksi
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool
     */
    public function kodeExists(string $kodeSeksi, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM seksi WHERE kode_seksi = :kode_seksi";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':kode_seksi', $kodeSeksi, PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking kode_seksi: ' . $e->getMessage());
            return false;
        }
    }
}

