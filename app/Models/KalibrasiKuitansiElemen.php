<?php

namespace App\Models;

use PDO;

class KalibrasiKuitansiElemen
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Semua posisi satu printer, keyed by elemen_key.
     * Fallback [] jika tabel/kolom belum ada — pemanggil pakai default config.
     */
    public function getAll(int $printerId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm` FROM `kalibrasi_kuitansi_elemen` WHERE `printer_id` = :pid');
            $stmt->execute([':pid' => $printerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[$r['elemen_key']] = [
                    'label' => $r['label'],
                    'x_mm' => (float) $r['x_mm'],
                    'y_mm' => (float) $r['y_mm'],
                    'max_width_mm' => $r['max_width_mm'] === null ? null : (float) $r['max_width_mm'],
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('KalibrasiKuitansiElemen::getAll fallback: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update per elemen_key untuk satu printer (upsert agar tahan jika ada key baru).
     * $items: [['elemen_key'=>..,'x_mm'=>..,'y_mm'=>..,'max_width_mm'=>..|null], ...]
     */
    public function saveAll(int $printerId, array $items, ?string $updatedBy): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO `kalibrasi_kuitansi_elemen` (`printer_id`, `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `updated_by`)
            VALUES (:pid, :k, :label, :x, :y, :w, :ub)
            ON DUPLICATE KEY UPDATE
                `x_mm` = VALUES(`x_mm`),
                `y_mm` = VALUES(`y_mm`),
                `max_width_mm` = VALUES(`max_width_mm`),
                `updated_by` = VALUES(`updated_by`)
        ");
        $count = 0;
        foreach ($items as $it) {
            $key = (string) ($it['elemen_key'] ?? '');
            if ($key === '' || strlen($key) > 40) {
                continue;
            }
            // Klem ke area kertas + margin toleransi.
            $x = max(-20, min(235, (float) ($it['x_mm'] ?? 0)));
            $y = max(-20, min(185, (float) ($it['y_mm'] ?? 0)));
            $w = $it['max_width_mm'] ?? null;
            $w = ($w === null || $w === '') ? null : (float) $w;
            if ($w !== null) {
                $w = max(10, min(215, $w));
            }
            $stmt->execute([
                ':pid' => $printerId,
                ':k' => $key,
                ':label' => substr((string) ($it['label'] ?? $key), 0, 100),
                ':x' => number_format($x, 2, '.', ''),
                ':y' => number_format($y, 2, '.', ''),
                ':w' => $w === null ? null : number_format($w, 2, '.', ''),
                ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
            ]);
            $count++;
        }
        return $count;
    }
}
