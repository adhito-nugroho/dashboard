<?php

namespace App\Models;

use PDO;

class KalibrasiKuitansi
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ambil satu-satunya baris kalibrasi. Jika belum ada (migrasi belum jalan),
     * kembalikan default 0,0 tanpa throw agar halaman cetak tetap bisa dibuka.
     */
    public function get(): array
    {
        try {
            $row = $this->db->query('SELECT * FROM `kalibrasi_kuitansi` ORDER BY `id` ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && $row) {
                return [
                    'id' => (int) ($row['id'] ?? 1),
                    'offset_x_mm' => (float) ($row['offset_x_mm'] ?? 0),
                    'offset_y_mm' => (float) ($row['offset_y_mm'] ?? 0),
                    'updated_at' => $row['updated_at'] ?? null,
                    'updated_by' => $row['updated_by'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            error_log('KalibrasiKuitansi::get fallback: ' . $e->getMessage());
        }
        return ['id' => 1, 'offset_x_mm' => 0.0, 'offset_y_mm' => 0.0, 'updated_at' => null, 'updated_by' => null];
    }

    /**
     * Update baris yang ada (bukan insert baru). Jika tabel kosong, insert 1 baris.
     */
    public function save(float $offsetX, float $offsetY, ?string $updatedBy): array
    {
        // Batasi rentang wajar agar salah ketik tidak merusak cetakan (±50mm).
        $offsetX = max(-50, min(50, $offsetX));
        $offsetY = max(-50, min(50, $offsetY));

        $existing = $this->db->query('SELECT `id` FROM `kalibrasi_kuitansi` ORDER BY `id` ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing) && isset($existing['id'])) {
            $stmt = $this->db->prepare('UPDATE `kalibrasi_kuitansi` SET `offset_x_mm` = :ox, `offset_y_mm` = :oy, `updated_by` = :ub WHERE `id` = :id');
            $stmt->execute([
                ':ox' => number_format($offsetX, 2, '.', ''),
                ':oy' => number_format($offsetY, 2, '.', ''),
                ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
                ':id' => (int) $existing['id'],
            ]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO `kalibrasi_kuitansi` (`offset_x_mm`, `offset_y_mm`, `updated_by`) VALUES (:ox, :oy, :ub)');
            $stmt->execute([
                ':ox' => number_format($offsetX, 2, '.', ''),
                ':oy' => number_format($offsetY, 2, '.', ''),
                ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
            ]);
        }
        return $this->get();
    }
}
