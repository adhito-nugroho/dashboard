<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Pejabat instansi tujuan pada cetak lembar 2 SPPD (satu baris per Surat Tugas).
 */
class SppdLembar2Pejabat
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{jabatan:string,nama:string,nip:string}|null */
    public function getBySuratTugasId(int $suratTugasId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT `jabatan`, `nama`, `nip` FROM `sppd_lembar2_pejabat` WHERE `surat_tugas_id` = ? LIMIT 1'
            );
            $stmt->execute([$suratTugasId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                'jabatan' => (string) $row['jabatan'],
                'nama' => (string) $row['nama'],
                'nip' => (string) $row['nip'],
            ];
        } catch (\Throwable $e) {
            error_log('SppdLembar2Pejabat::getBySuratTugasId: ' . $e->getMessage());
            return null;
        }
    }

    public function save(int $suratTugasId, string $jabatan, string $nama, string $nip, ?string $updatedBy): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `sppd_lembar2_pejabat` (`surat_tugas_id`, `jabatan`, `nama`, `nip`, `updated_by`)
             VALUES (:st, :jabatan, :nama, :nip, :ub)
             ON DUPLICATE KEY UPDATE
                `jabatan` = VALUES(`jabatan`),
                `nama`    = VALUES(`nama`),
                `nip`     = VALUES(`nip`),
                `updated_by` = VALUES(`updated_by`)'
        );
        $stmt->execute([
            ':st' => $suratTugasId,
            ':jabatan' => $jabatan,
            ':nama' => $nama,
            ':nip' => $nip,
            ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
        ]);
    }
}
