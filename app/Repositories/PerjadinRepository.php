<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

/**
 * Repository (read-only) untuk data pendukung SPJ Perjalanan Dinas di
 * db_anggaran: mencari transaksi perjalanan dinas terkait (untuk mengambil
 * kode rekening / akun dan lokasi kegiatan).
 */
class PerjadinRepository
{
    private ?PDO $pdo;
    private bool $resolved;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->resolved = $pdo !== null;
    }

    private function connection(): ?PDO
    {
        if (!$this->resolved) {
            $this->resolved = true;
            try {
                $cfg = __DIR__ . '/../../config/database.php';
                if (!class_exists('Database') && is_file($cfg)) {
                    require_once $cfg;
                }
                $this->pdo = class_exists('Database') ? \Database::getConnection() : null;
            } catch (Throwable $e) {
                error_log('PerjadinRepository: koneksi db_anggaran gagal: ' . $e->getMessage());
                $this->pdo = null;
            }
        }

        return $this->pdo;
    }

    /**
     * Cari transaksi perjalanan dinas untuk (ST + pegawai).
     *
     * @return array{lokasi_kegiatan?:string, kode_rekening?:string}|null
     */
    public function findSpdAccount(int $suratTugasId, string $nomorSurat, string $nip, string $nama): ?array
    {
        $pdo = $this->connection();
        if ($pdo === null) {
            return null;
        }

        try {
            $sql = "
                SELECT t.lokasi_kegiatan, r.kode_rekening
                FROM transaksi t
                LEFT JOIN rekening r ON r.id = t.rekening_id
                WHERE t.jenis_transaksi = 'perjalanan_dinas'
                  AND (t.surat_tugas_ref_id = :st
                       OR t.nomor_surat_tugas = :no)
                  AND (REPLACE(t.pegawai_nip, ' ', '') = REPLACE(:nip, ' ', '')
                       OR t.nama_penerima = :nama
                       OR t.nama_penerima LIKE :nama_like)
                ORDER BY t.id DESC LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':st' => $suratTugasId,
                ':no' => $nomorSurat,
                ':nip' => $nip,
                ':nama' => $nama,
                ':nama_like' => '%' . $nama . '%',
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('PerjadinRepository::findSpdAccount error: ' . $e->getMessage());
            return null;
        }
    }
}
