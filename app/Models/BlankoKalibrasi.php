<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Pustaka blanko kalibrasi: menyimpan riwayat file contoh kuitansi dan
 * lembar 2 SPPD yang pernah diupload. Satu baris aktif per jenis dipakai
 * sebagai background kanvas; file lain tetap tersimpan untuk dipakai ulang.
 */
class BlankoKalibrasi
{
    public const JENIS_KUITANSI = 'kuitansi';
    public const JENIS_SPPD     = 'sppd_lembar2';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function normalizeJenis(string $jenis): string
    {
        return $jenis === self::JENIS_SPPD ? self::JENIS_SPPD : self::JENIS_KUITANSI;
    }

    /** Direktori penyimpanan file pustaka untuk satu jenis blanko. */
    public static function dir(string $jenis): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/kalibrasi/pustaka/' . self::normalizeJenis($jenis);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getAll(string $jenis): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM kalibrasi_blanko
                WHERE jenis = :jenis
                ORDER BY is_active DESC, created_at DESC, id DESC
            ");
            $stmt->execute([':jenis' => self::normalizeJenis($jenis)]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching pustaka blanko: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM kalibrasi_blanko WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching blanko by id: ' . $e->getMessage());
            return null;
        }
    }

    public function getActive(string $jenis): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM kalibrasi_blanko
                WHERE jenis = :jenis AND is_active = 1
                ORDER BY created_at DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([':jenis' => self::normalizeJenis($jenis)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching active blanko: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Simpan file baru ke pustaka sekaligus jadikan aktif.
     */
    public function store(
        string $jenis,
        string $namaFile,
        string $namaAsli,
        string $kind,
        int $ukuran,
        ?string $uploadedBy
    ): int {
        $jenis = self::normalizeJenis($jenis);
        $kind  = $kind === 'pdf' ? 'pdf' : 'image';

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE kalibrasi_blanko SET is_active = 0 WHERE jenis = :jenis')
                ->execute([':jenis' => $jenis]);

            $stmt = $this->db->prepare("
                INSERT INTO kalibrasi_blanko
                    (jenis, nama_file, nama_asli, kind, ukuran, uploaded_by, is_active)
                VALUES
                    (:jenis, :nama_file, :nama_asli, :kind, :ukuran, :uploaded_by, 1)
            ");
            $stmt->execute([
                ':jenis'       => $jenis,
                ':nama_file'   => $namaFile,
                ':nama_asli'   => $namaAsli,
                ':kind'        => $kind,
                ':ukuran'      => $ukuran,
                ':uploaded_by' => $uploadedBy,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Error storing blanko: ' . $e->getMessage());
            throw new \RuntimeException('Gagal menyimpan blanko ke pustaka: ' . $e->getMessage());
        }
    }

    /** Jadikan satu file pustaka sebagai blanko aktif. */
    public function activate(int $id): bool
    {
        $row = $this->getById($id);
        if ($row === null) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE kalibrasi_blanko SET is_active = 0 WHERE jenis = :jenis')
                ->execute([':jenis' => $row['jenis']]);
            $this->db->prepare('UPDATE kalibrasi_blanko SET is_active = 1 WHERE id = :id')
                ->execute([':id' => $id]);
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error activating blanko: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus satu file dari pustaka. Bila yang dihapus sedang aktif, file
     * terbaru yang tersisa otomatis dijadikan aktif.
     */
    public function delete(int $id): bool
    {
        $row = $this->getById($id);
        if ($row === null) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM kalibrasi_blanko WHERE id = :id')->execute([':id' => $id]);

            if ((int) $row['is_active'] === 1) {
                $this->db->prepare("
                    UPDATE kalibrasi_blanko
                    SET is_active = 1
                    WHERE jenis = :jenis
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ")->execute([':jenis' => $row['jenis']]);
            }
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error deleting blanko: ' . $e->getMessage());
            return false;
        }
    }
}
