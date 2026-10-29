<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Posisi elemen cetak lembar 2 SPPD per printer (mm pada halaman A4).
 * max_width_mm / max_lines NULL = ikut default config.
 */
class KalibrasiSppdLembar2Elemen
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string,array{x_mm:float,y_mm:float,max_width_mm:?float,max_lines:?int}> */
    public function getAll(int $printerId): array
    {
        if ($printerId <= 0) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `max_lines`
                 FROM `kalibrasi_sppd_lembar2_elemen` WHERE `printer_id` = :pid'
            );
            $stmt->execute([':pid' => $printerId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $w = $r['max_width_mm'] ?? null;
                $lines = $r['max_lines'] ?? null;
                $out[(string) $r['elemen_key']] = [
                    'x_mm' => (float) $r['x_mm'],
                    'y_mm' => (float) $r['y_mm'],
                    'max_width_mm' => ($w === null || $w === '') ? null : (float) $w,
                    'max_lines' => ($lines === null || $lines === '') ? null : (int) $lines,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('KalibrasiSppdLembar2Elemen::getAll fallback: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Upsert posisi semua elemen satu printer.
     * $items: [['elemen_key'=>..,'x_mm'=>..,'y_mm'=>..,'max_width_mm'=>..|null,'max_lines'=>..|null], ...]
     */
    public function saveAll(int $printerId, array $items, ?string $updatedBy): int
    {
        if ($printerId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("
            INSERT INTO `kalibrasi_sppd_lembar2_elemen`
                (`printer_id`, `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `max_lines`, `updated_by`)
            VALUES (:pid, :k, :label, :x, :y, :w, :lines, :ub)
            ON DUPLICATE KEY UPDATE
                `x_mm` = VALUES(`x_mm`),
                `y_mm` = VALUES(`y_mm`),
                `max_width_mm` = VALUES(`max_width_mm`),
                `max_lines` = VALUES(`max_lines`),
                `updated_by` = VALUES(`updated_by`)
        ");

        $count = 0;
        foreach ($items as $it) {
            $key = (string) ($it['elemen_key'] ?? '');
            if ($key === '' || strlen($key) > 40) {
                continue;
            }
            // Klem ke area A4 + sedikit toleransi.
            $x = max(-10, min(215, (float) ($it['x_mm'] ?? 0)));
            $y = max(-10, min(300, (float) ($it['y_mm'] ?? 0)));
            $w = $it['max_width_mm'] ?? null;
            $w = ($w === null || $w === '') ? null : max(5.0, min(210.0, (float) $w));
            $lines = $it['max_lines'] ?? null;
            $lines = ($lines === null || $lines === '') ? null : max(1, min(5, (int) $lines));

            $stmt->execute([
                ':pid' => $printerId,
                ':k' => $key,
                ':label' => substr((string) ($it['label'] ?? $key), 0, 100),
                ':x' => number_format($x, 2, '.', ''),
                ':y' => number_format($y, 2, '.', ''),
                ':w' => $w === null ? null : number_format($w, 2, '.', ''),
                ':lines' => $lines,
                ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
            ]);
            $count++;
        }
        return $count;
    }
}
