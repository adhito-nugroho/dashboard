<?php

namespace App\Models;

use PDO;
use PDOException;

class Program {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all programs
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM program ORDER BY tahun DESC, kode_program ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching programs: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch programs');
        }
    }
    
    /**
     * Get program by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM program WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching program: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch program');
        }
    }
    
    /**
     * Create new program
     * 
     * @param string $kodeProgram
     * @param string $namaProgram
     * @param int $tahun
     * @return int Inserted ID
     */
    public function create(string $kodeProgram, string $namaProgram, int $tahun): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO program (kode_program, nama_program, tahun) 
                VALUES (:kode_program, :nama_program, :tahun)
            ");
            $stmt->bindParam(':kode_program', $kodeProgram, PDO::PARAM_STR);
            $stmt->bindParam(':nama_program', $namaProgram, PDO::PARAM_STR);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating program: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create program');
        }
    }
    
    /**
     * Update program
     * 
     * @param int $id
     * @param string $kodeProgram
     * @param string $namaProgram
     * @param int $tahun
     * @return bool
     */
    public function update(int $id, string $kodeProgram, string $namaProgram, int $tahun): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE program 
                SET kode_program = :kode_program, 
                    nama_program = :nama_program, 
                    tahun = :tahun 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':kode_program', $kodeProgram, PDO::PARAM_STR);
            $stmt->bindParam(':nama_program', $namaProgram, PDO::PARAM_STR);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating program: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update program');
        }
    }
    
    /**
     * Delete program
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM program WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting program: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete program');
        }
    }
    
    /**
     * Check if kode_program exists (for validation)
     * 
     * @param string $kodeProgram
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool
     */
    public function kodeExists(string $kodeProgram, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM program WHERE kode_program = :kode_program";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':kode_program', $kodeProgram, PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking kode_program: ' . $e->getMessage());
            return false;
        }
    }
}

