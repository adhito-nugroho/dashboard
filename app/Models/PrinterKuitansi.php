<?php

namespace App\Models;

use PDO;

class PrinterKuitansi
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Daftar printer: [['id','nama','keterangan','is_default','windows_printer_name','paper_form_name'], ...] */
    public function getAll(): array
    {
        try {
            return $this->db->query('SELECT `id`, `nama`, `keterangan`, `is_default`, `windows_printer_name`, `paper_form_name` FROM `printer_kuitansi` ORDER BY `id`')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterKuitansi::getAll fallback: ' . $e->getMessage());
            return [];
        }
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT `id`, `nama`, `keterangan`, `is_default`, `windows_printer_name`, `paper_form_name` FROM `printer_kuitansi` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getDefaultId(): int
    {
        try {
            $id = (int) $this->db->query('SELECT `id` FROM `printer_kuitansi` WHERE `is_default` = 1 LIMIT 1')->fetchColumn();
            if ($id > 0) {
                return $id;
            }
            $id = (int) $this->db->query('SELECT `id` FROM `printer_kuitansi` ORDER BY `id` LIMIT 1')->fetchColumn();
            return $id > 0 ? $id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Tambah printer baru. Posisi awal disalin dari printer default
     * (atau config default bila sumber kosong) agar tidak mulai dari nol.
     * Return id baru, atau 0 bila nama duplikat/kosong.
     */
    public function create(string $nama, ?string $keterangan, array $sourcePositions = [], ?string $windowsPrinterName = null, ?string $paperFormName = 'Kuitansi'): int
    {
        $nama = trim($nama);
        if ($nama === '' || strlen($nama) > 100) {
            return 0;
        }
        try {
            $stmt = $this->db->prepare('
                INSERT INTO `printer_kuitansi` 
                    (`nama`, `keterangan`, `is_default`, `windows_printer_name`, `paper_form_name`) 
                VALUES 
                    (:nama, :ket, 0, :win_printer, :paper_form)
            ');
            $stmt->execute([
                ':nama' => $nama,
                ':ket' => $keterangan !== null && $keterangan !== '' ? substr($keterangan, 0, 255) : null,
                ':win_printer' => $windowsPrinterName !== null && $windowsPrinterName !== '' ? substr($windowsPrinterName, 0, 150) : null,
                ':paper_form' => $paperFormName !== null && $paperFormName !== '' ? substr($paperFormName, 0, 50) : 'Kuitansi',
            ]);
        } catch (\PDOException $e) {
            error_log('PrinterKuitansi::create: ' . $e->getMessage());
            return 0;
        }
        $newId = (int) $this->db->lastInsertId();
        if ($newId > 0 && !empty($sourcePositions)) {
            $ins = $this->db->prepare("
                INSERT INTO `kalibrasi_kuitansi_elemen`
                    (`printer_id`, `elemen_key`, `label`, `x_mm`, `y_mm`, `max_width_mm`, `updated_by`)
                VALUES (:pid, :k, :label, :x, :y, :w, 'salin-printer')
                ON DUPLICATE KEY UPDATE
                    `x_mm` = VALUES(`x_mm`), `y_mm` = VALUES(`y_mm`),
                    `max_width_mm` = VALUES(`max_width_mm`)
            ");
            foreach ($sourcePositions as $key => $v) {
                if (!is_array($v)) {
                    continue;
                }
                $ins->execute([
                    ':pid' => $newId,
                    ':k' => substr((string) $key, 0, 40),
                    ':label' => substr((string) ($v['label'] ?? $key), 0, 100),
                    ':x' => number_format((float) ($v['x_mm'] ?? 0), 2, '.', ''),
                    ':y' => number_format((float) ($v['y_mm'] ?? 0), 2, '.', ''),
                    ':w' => ($v['max_width_mm'] ?? null) === null ? null : number_format((float) $v['max_width_mm'], 2, '.', ''),
                ]);
            }
        }
        return $newId;
    }

    /** Update pengaturan Windows printer dan form kertas. */
    public function updateWindowsSettings(int $id, ?string $windowsPrinterName, ?string $paperFormName): bool
    {
        if ($this->get($id) === null) {
            return false;
        }
        try {
            $stmt = $this->db->prepare('
                UPDATE `printer_kuitansi` 
                SET `windows_printer_name` = :win_printer,
                    `paper_form_name` = :paper_form
                WHERE `id` = :id
            ');
            return $stmt->execute([
                ':id' => $id,
                ':win_printer' => $windowsPrinterName !== null && trim($windowsPrinterName) !== '' ? trim(substr($windowsPrinterName, 0, 150)) : null,
                ':paper_form' => $paperFormName !== null && trim($paperFormName) !== '' ? trim(substr($paperFormName, 0, 50)) : 'Kuitansi',
            ]);
        } catch (\PDOException $e) {
            error_log('PrinterKuitansi::updateWindowsSettings: ' . $e->getMessage());
            return false;
        }
    }

    /** Jadikan default (tepat satu). Return true bila id ada. */
    public function setDefault(int $id): bool
    {
        if ($this->get($id) === null) {
            return false;
        }
        $this->db->exec('UPDATE `printer_kuitansi` SET `is_default` = 0');
        $stmt = $this->db->prepare('UPDATE `printer_kuitansi` SET `is_default` = 1 WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        return true;
    }

    /** Hapus printer (bukan default). Kalibrasinya ikut terhapus. */
    public function delete(int $id): bool
    {
        $row = $this->get($id);
        if ($row === null || !empty($row['is_default'])) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM `kalibrasi_kuitansi_elemen` WHERE `printer_id` = :id');
        $stmt->execute([':id' => $id]);
        $stmt = $this->db->prepare('DELETE FROM `printer_kuitansi` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        return true;
    }
}
