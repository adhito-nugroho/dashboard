<?php

namespace App\Models;

use PDO;
use PDOException;

class Rekening {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all accounts with sub-activity information
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, 
                       sk.id as sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id,
                       k.id as kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.id as program_id, p.kode_program, p.nama_program, p.tahun 
                FROM rekening r
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                ORDER BY p.tahun DESC, p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, r.kode_rekening ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching accounts: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch accounts');
        }
    }
    
    /**
     * Get account by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, 
                       sk.id as sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id,
                       k.id as kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.id as program_id, p.kode_program, p.nama_program, p.tahun 
                FROM rekening r
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                WHERE r.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching account: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch account');
        }
    }
    
    /**
     * Get accounts by sub-activity ID
     * 
     * @param int $subKegiatanId
     * @return array
     */
    public function getBySubKegiatanId(int $subKegiatanId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, sk.seksi_id
                FROM rekening r
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                WHERE r.sub_kegiatan_id = :sub_kegiatan_id 
                ORDER BY r.kode_rekening ASC
            ");
            $stmt->bindParam(':sub_kegiatan_id', $subKegiatanId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching accounts by sub-activity: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch accounts');
        }
    }

    /**
     * Create new account
     * 
     * @param int $subKegiatanId
     * @param string $kodeRekening
     * @param string $namaRekening
     * @return int Inserted ID
     */
    public function create(int $subKegiatanId, string $kodeRekening, string $namaRekening): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO rekening (sub_kegiatan_id, kode_rekening, nama_rekening)
                VALUES (:sub_kegiatan_id, :kode_rekening, :nama_rekening)
            ");
            $stmt->bindParam(':sub_kegiatan_id', $subKegiatanId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_rekening', $kodeRekening, PDO::PARAM_STR);
            $stmt->bindParam(':nama_rekening', $namaRekening, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating account: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create account');
        }
    }

    /**
     * Update account
     * 
     * @param int $id
     * @param int $subKegiatanId
     * @param string $kodeRekening
     * @param string $namaRekening
     * @return bool
     */
    public function update(int $id, int $subKegiatanId, string $kodeRekening, string $namaRekening): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE rekening
                SET sub_kegiatan_id = :sub_kegiatan_id,
                    kode_rekening = :kode_rekening,
                    nama_rekening = :nama_rekening
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':sub_kegiatan_id', $subKegiatanId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_rekening', $kodeRekening, PDO::PARAM_STR);
            $stmt->bindParam(':nama_rekening', $namaRekening, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating account: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update account');
        }
    }

    /**
     * Delete account
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM rekening WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting account: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete account');
        }
    }

    /**
     * Check if kode_rekening exists for the same sub_kegiatan_id (for validation)
     * Kode rekening yang sama boleh digunakan di sub kegiatan yang berbeda
     * 
     * @param int $subKegiatanId
     * @param string $kodeRekening
     * @param int|null $excludeId
     * @return bool
     */
    public function kodeExists(int $subKegiatanId, string $kodeRekening, ?int $excludeId = null): bool {
        try {
            $sql = "SELECT COUNT(*) FROM rekening 
                    WHERE sub_kegiatan_id = :sub_kegiatan_id 
                    AND kode_rekening = :kode_rekening";
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':sub_kegiatan_id', $subKegiatanId, PDO::PARAM_INT);
            $stmt->bindParam(':kode_rekening', $kodeRekening, PDO::PARAM_STR);
            if ($excludeId !== null) {
                $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log('Error checking kode_rekening: ' . $e->getMessage());
            return false;
        }
    }
}

