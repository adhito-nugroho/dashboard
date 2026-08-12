<?php

namespace App\Models;

use PDO;
use PDOException;

class Rak {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get all monthly cash plans with related information
     * 
     * @return array
     */
    public function getAll(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, 
                       rek.kode_rekening, rek.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM rak r
                INNER JOIN rekening rek ON r.rekening_id = rek.id
                INNER JOIN sub_kegiatan sk ON rek.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                ORDER BY r.tahun DESC, p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, rek.kode_rekening ASC, r.bulan ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching monthly cash plans: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch monthly cash plans');
        }
    }
    
    /**
     * Get RAK with flexible filters (tahun, kegiatan_id, sub_kegiatan_id)
     *
     * @param int|null $tahun
     * @param int|null $kegiatanId
     * @param int|null $subKegiatanId
     * @return array
     */
    public function getWithFilters(?int $tahun = null, ?int $kegiatanId = null, ?int $subKegiatanId = null): array {
        try {
            $conditions = [];
            $params = [];

            if ($tahun !== null) {
                $conditions[] = 'r.tahun = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($subKegiatanId !== null) {
                $conditions[] = 'rek.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $stmt = $this->db->prepare("
                SELECT r.*, 
                       rek.kode_rekening, rek.nama_rekening,
                       sk.id as sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.id as kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM rak r
                INNER JOIN rekening rek ON r.rekening_id = rek.id
                INNER JOIN sub_kegiatan sk ON rek.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                {$where}
                ORDER BY r.tahun DESC, p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, rek.kode_rekening ASC, r.bulan ASC
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching RAK with filters: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch RAK');
        }
    }

    /**
     * Get all RAK filtered by year
     *
     * @param int $tahun
     * @return array
     */
    public function getByYear(int $tahun): array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, 
                       rek.kode_rekening, rek.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM rak r
                INNER JOIN rekening rek ON r.rekening_id = rek.id
                INNER JOIN sub_kegiatan sk ON rek.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                WHERE r.tahun = :tahun
                ORDER BY p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, rek.kode_rekening ASC, r.bulan ASC
            ");
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching RAK by year: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch RAK by year');
        }
    }

    /**
     * Get monthly cash plans by rekening and year
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @return array
     */
    public function getByRekeningAndYear(int $rekeningId, int $tahun): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM rak 
                WHERE rekening_id = :rekening_id AND tahun = :tahun 
                ORDER BY bulan ASC
            ");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching monthly cash plans: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch monthly cash plans');
        }
    }
    
    /**
     * Get monthly cash plan by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, 
                       rek.kode_rekening, rek.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM rak r
                INNER JOIN rekening rek ON r.rekening_id = rek.id
                INNER JOIN sub_kegiatan sk ON rek.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                WHERE r.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching monthly cash plan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch monthly cash plan');
        }
    }
    
    /**
     * Create or update monthly cash plans (bulk operation)
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @param array $bulanData Array of [bulan => nilai_rak]
     * @return bool
     */
    public function saveMonthlyData(int $rekeningId, int $tahun, array $bulanData): bool {
        try {
            $this->db->beginTransaction();
            
            // Delete existing data for this rekening and year
            $deleteStmt = $this->db->prepare("DELETE FROM rak WHERE rekening_id = :rekening_id AND tahun = :tahun");
            $deleteStmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $deleteStmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Insert new data
            $insertStmt = $this->db->prepare("
                INSERT INTO rak (rekening_id, tahun, bulan, nilai_rak) 
                VALUES (:rekening_id, :tahun, :bulan, :nilai_rak)
            ");
            
            foreach ($bulanData as $bulan => $nilaiRak) {
                if ($nilaiRak > 0) { // Only insert if value > 0
                    $insertStmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
                    $insertStmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
                    $insertStmt->bindParam(':bulan', $bulan, PDO::PARAM_INT);
                    $insertStmt->bindParam(':nilai_rak', $nilaiRak, PDO::PARAM_STR);
                    $insertStmt->execute();
                }
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error saving monthly cash plans: ' . $e->getMessage());
            throw new \RuntimeException('Failed to save monthly cash plans');
        }
    }
    
    /**
     * Get total RAK for rekening and year
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @return float
     */
    public function getTotalByRekeningAndYear(int $rekeningId, int $tahun): float {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(nilai_rak), 0) as total 
                FROM rak 
                WHERE rekening_id = :rekening_id AND tahun = :tahun
            ");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float) ($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log('Error calculating total RAK: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete monthly cash plans by rekening and year
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @return bool
     */
    public function deleteByRekeningAndYear(int $rekeningId, int $tahun): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM rak WHERE rekening_id = :rekening_id AND tahun = :tahun");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting monthly cash plans: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete monthly cash plans');
        }
    }
}

