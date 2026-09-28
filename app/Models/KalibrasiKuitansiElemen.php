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
            // is_wrap mungkin belum ada (migrasi belum jalan) — deteksi kolom dulu.
            $hasWrap = true;
            try {
                $c = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
                $c->execute(['kalibrasi_kuitansi_elemen', 'is_wrap']);
                $hasWrap = (int) $c->fetchColumn() > 0;
            } catch (\Throwable $e) {
                $hasWrap = false;
            }
            $sel = $hasWrap
                ? 'SELECT `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `is_wrap` FROM `kalibrasi_kuitansi_elemen` WHERE `printer_id` = :pid'
                : 'SELECT `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm` FROM `kalibrasi_kuitansi_elemen` WHERE `printer_id` = :pid';
            $stmt = $this->db->prepare($sel);
            $stmt->execute([':pid' => $printerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $w = $r['max_width_mm'] ?? null;
                $wrap = $r['is_wrap'] ?? null;
                $out[$r['elemen_key']] = [
                    'label' => $r['label'],
                    'x_mm' => (float) $r['x_mm'],
                    'y_mm' => (float) $r['y_mm'],
                    'max_width_mm' => ($w === null || $w === '') ? null : (float) $w,
                    'is_wrap' => ($wrap === null || $wrap === '') ? null : (int) $wrap,
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
        // Deteksi kolom is_wrap (tahan bila migrasi 20260928 belum dijalankan).
        $hasWrap = true;
        try {
            $c = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $c->execute(['kalibrasi_kuitansi_elemen', 'is_wrap']);
            $hasWrap = (int) $c->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $hasWrap = false;
        }
        $sql = $hasWrap
            ? "
            INSERT INTO `kalibrasi_kuitansi_elemen` (`printer_id`, `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `is_wrap`, `updated_by`)
            VALUES (:pid, :k, :label, :x, :y, :w, :wrap, :ub)
            ON DUPLICATE KEY UPDATE
                `x_mm` = VALUES(`x_mm`),
                `y_mm` = VALUES(`y_mm`),
                `max_width_mm` = VALUES(`max_width_mm`),
                `is_wrap` = VALUES(`is_wrap`),
                `updated_by` = VALUES(`updated_by`)
            "
            : "
            INSERT INTO `kalibrasi_kuitansi_elemen` (`printer_id`, `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `updated_by`)
            VALUES (:pid, :k, :label, :x, :y, :w, :ub)
            ON DUPLICATE KEY UPDATE
                `x_mm` = VALUES(`x_mm`),
                `y_mm` = VALUES(`y_mm`),
                `max_width_mm` = VALUES(`max_width_mm`),
                `updated_by` = VALUES(`updated_by`)
            ";
        $stmt = $this->db->prepare($sql);
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
                $w = max(5, min(215, $w));
            }
            $wrapRaw = $it['is_wrap'] ?? null;
            $wrap = ($wrapRaw === null || $wrapRaw === '') ? null : ((int) $wrapRaw ? 1 : 0);
            $params = [
                ':pid' => $printerId,
                ':k' => $key,
                ':label' => substr((string) ($it['label'] ?? $key), 0, 100),
                ':x' => number_format($x, 2, '.', ''),
                ':y' => number_format($y, 2, '.', ''),
                ':w' => $w === null ? null : number_format($w, 2, '.', ''),
                ':ub' => $updatedBy !== null && $updatedBy !== '' ? substr($updatedBy, 0, 100) : null,
            ];
            if ($hasWrap) {
                $params[':wrap'] = $wrap;
            }
            $stmt->execute($params);
            $count++;
        }
        return $count;
    }
}
