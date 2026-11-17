<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Pejabat penandatangan pada cetak lembar 2 SPPD (satu baris per Surat Tugas):
 * pejabat instansi tujuan, Kepala CDK Wilayah Bojonegoro, dan Kuasa Pengguna
 * Anggaran.
 */
class SppdLembar2Pejabat
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{jabatan:string,nama:string,nip:string,kepala_jabatan:string,kepala_nama:string,kepala_nip:string,kpa_nama:string,kpa_nip:string,tampilkan_nip:bool}|null */
    public function getBySuratTugasId(int $suratTugasId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT `jabatan`, `nama`, `nip`, `kepala_jabatan`, `kepala_nama`, `kepala_nip`,
                        `kpa_nama`, `kpa_nip`, `tampilkan_nip`
                 FROM `sppd_lembar2_pejabat` WHERE `surat_tugas_id` = ? LIMIT 1'
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
                'kepala_jabatan' => (string) ($row['kepala_jabatan'] ?? ''),
                'kepala_nama' => (string) ($row['kepala_nama'] ?? ''),
                'kepala_nip' => (string) ($row['kepala_nip'] ?? ''),
                'kpa_nama' => (string) ($row['kpa_nama'] ?? ''),
                'kpa_nip' => (string) ($row['kpa_nip'] ?? ''),
                'tampilkan_nip' => (bool) (int) ($row['tampilkan_nip'] ?? 1),
            ];
        } catch (\Throwable $e) {
            error_log('SppdLembar2Pejabat::getBySuratTugasId: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array{jabatan:string,nama:string,nip:string,kepala_jabatan:string,kepala_nama:string,kepala_nip:string,kpa_nama:string,kpa_nip:string,tampilkan_nip:bool} $data
     */
    public function save(int $suratTugasId, array $data, ?string $updatedBy): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `sppd_lembar2_pejabat`
                (`surat_tugas_id`, `jabatan`, `nama`, `nip`,
                 `kepala_jabatan`, `kepala_nama`, `kepala_nip`, `kpa_nama`, `kpa_nip`,
                 `tampilkan_nip`, `updated_by`)
             VALUES (:st, :jabatan, :nama, :nip,
                     :kj, :kn, :knip, :kpan, :kpanip,
                     :tnip, :ub)
             ON DUPLICATE KEY UPDATE
                `jabatan` = VALUES(`jabatan`),
                `nama`    = VALUES(`nama`),
                `nip`     = VALUES(`nip`),
                `kepala_jabatan` = VALUES(`kepala_jabatan`),
                `kepala_nama` = VALUES(`kepala_nama`),
                `kepala_nip` = VALUES(`kepala_nip`),
                `kpa_nama` = VALUES(`kpa_nama`),
                `kpa_nip` = VALUES(`kpa_nip`),
                `tampilkan_nip` = VALUES(`tampilkan_nip`),
                `updated_by` = VALUES(`updated_by`)'
        );
        $stmt->execute([
            ':st' => $suratTugasId,
            ':jabatan' => (string) ($data['jabatan'] ?? ''),
            ':nama' => (string) ($data['nama'] ?? ''),
            ':nip' => (string) ($data['nip'] ?? ''),
            ':kj' => (string) ($data['kepala_jabatan'] ?? ''),
            ':kn' => (string) ($data['kepala_nama'] ?? ''),
            ':knip' => (string) ($data['kepala_nip'] ?? ''),
            ':kpan' => (string) ($data['kpa_nama'] ?? ''),
            ':kpanip' => (string) ($data['kpa_nip'] ?? ''),
            ':tnip' => !empty($data['tampilkan_nip']) ? 1 : 0,
            ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
        ]);
    }
}
