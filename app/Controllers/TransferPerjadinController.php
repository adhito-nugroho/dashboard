<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/database_surat_tugas.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\TransferPerjadin;
use App\Services\TransferPerjadinExportService;

/**
 * Modul Transfer Perjalanan Dinas (admin/bendahara only).
 *
 * Alur:
 *   /transfer-perjadin                 → index()  : daftar Surat Tugas + status konfigurasi
 *   /transfer-perjadin/atur/{st_id}    → atur()   : pilih penerima transfer + input potongan
 *   /transfer-perjadin/save            → save()   : simpan konfigurasi satu ST (POST)
 *   /transfer-perjadin/export          → export() : unduh Excel gabungan (Rekap + Detail)
 *
 * Penerimaan (gross) dihitung dari transaksi perjalanan dinas (db_anggaran),
 * potongan diinput manual, penerimaan bersih = penerimaan - potongan.
 */
class TransferPerjadinController
{
    private TransferPerjadin $model;

    public function __construct(TransferPerjadin $model)
    {
        $this->model = $model;
    }

    // ──────────────────────────────────────────────────────────
    // GUARD
    // ──────────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['is_admin'])) {
            $_SESSION['flash_message'] = 'Akses ditolak: fitur Transfer Perjalanan Dinas hanya untuk admin/bendahara.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    // ──────────────────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAdmin();

        $pdo = \DatabaseSuratTugas::getConnection();
        $suratTugasList = [];
        $pegawaiCounts = [];
        $stError = null;

        $q = trim((string) ($_GET['q'] ?? ''));
        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');

        if ($pdo === null) {
            $stError = 'Koneksi ke database Surat Tugas tidak tersedia.';
        } else {
            try {
                $conditions = [];
                $params = [];
                if ($q !== '') {
                    $conditions[] = '(nomor_surat LIKE :kw_no OR untuk LIKE :kw_untuk)';
                    $params[':kw_no'] = '%' . $q . '%';
                    $params[':kw_untuk'] = '%' . $q . '%';
                }
                if ($bulan !== null) {
                    $conditions[] = 'MONTH(tanggal_mulai) = :bulan';
                    $params[':bulan'] = $bulan;
                }
                $conditions[] = 'YEAR(tanggal_mulai) = :tahun';
                $params[':tahun'] = $tahun;

                $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
                $stmt = $pdo->prepare("
                    SELECT id, nomor_surat, tanggal_surat, untuk,
                           tanggal_mulai, tanggal_selesai
                    FROM surat_tugas
                    {$where}
                    ORDER BY tanggal_mulai DESC, id DESC
                    LIMIT 200
                ");
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                }
                $stmt->execute();
                $suratTugasList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if ($suratTugasList !== []) {
                    $ids = array_map(static fn(array $r): int => (int) $r['id'], $suratTugasList);
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pStmt = $pdo->prepare("
                        SELECT id_surat_tugas, COUNT(*) AS jml
                        FROM pegawai_tugas
                        WHERE id_surat_tugas IN ($ph)
                        GROUP BY id_surat_tugas
                    ");
                    $pStmt->execute($ids);
                    foreach ($pStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $pegawaiCounts[(int) $r['id_surat_tugas']] = (int) $r['jml'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('TransferPerjadinController::index ST error: ' . $e->getMessage());
                $stError = 'Gagal memuat daftar Surat Tugas. Silakan coba beberapa saat lagi.';
            }
        }

        $configuredCounts = $this->model->getConfiguredCounts();

        $pageTitle = 'Transfer Perjalanan Dinas';
        $activePage = 'transfer_perjadin';
        $viewFile = __DIR__ . '/../../views/transfer_perjadin/index.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // ATUR
    // ──────────────────────────────────────────────────────────

    public function atur(int $suratTugasId): void
    {
        $this->requireAdmin();

        $stData = $this->assembleSt($suratTugasId);
        if ($stData === null) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Surat Tugas tidak ditemukan atau tidak dapat diakses.');
            return;
        }

        $st = $stData['st'];
        $pegawaiList = $stData['pegawai'];

        $pageTitle = 'Atur Transfer — ' . ($st['nomor_surat'] ?? '');
        $activePage = 'transfer_perjadin';
        $viewFile = __DIR__ . '/../../views/transfer_perjadin/atur.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // SAVE
    // ──────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->requireAdmin();

        $suratTugasId = (int) ($_POST['surat_tugas_id'] ?? 0);
        $pdo = \DatabaseSuratTugas::getConnection();
        $st = ($pdo !== null && $suratTugasId > 0) ? $this->fetchSt($pdo, $suratTugasId) : null;

        if ($st === null) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Surat Tugas tidak valid.');
            return;
        }

        $pekerja = $this->fetchPegawai($pdo, $suratTugasId);
        if ($pekerja === []) {
            $this->redirectWithMessage(base_url('transfer-perjadin/atur/' . $suratTugasId), 'error', 'Surat Tugas ini belum memiliki data pegawai.');
            return;
        }

        $transferKe = trim((string) ($_POST['transfer_ke'] ?? ''));
        $potonganInput = $_POST['potongan'] ?? [];
        if (!is_array($potonganInput)) {
            $potonganInput = [];
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $rows = [];
        foreach ($pekerja as $p) {
            $nip = trim((string) $p['nip']);
            $isKe = ($transferKe !== '' && $transferKe === $nip);
            $raw = $potonganInput[$nip] ?? ($potonganInput[$p['nip']] ?? 0);
            $potongan = $this->parseRupiah($raw);

            if (!$isKe && $potongan <= 0) {
                continue;
            }
            $rows[] = [
                'nip' => $nip,
                'nama' => trim((string) $p['nama']),
                'potongan' => $potongan,
                'is_transfer_ke' => $isKe,
            ];
        }

        try {
            $this->model->saveForSuratTugas($suratTugasId, (string) $st['nomor_surat'], $rows, $userId);
            $this->redirectWithMessage(
                base_url('transfer-perjadin/atur/' . $suratTugasId),
                'success',
                $rows === []
                    ? 'Konfigurasi transfer dibersihkan untuk Surat Tugas ini.'
                    : 'Konfigurasi transfer tersimpan (' . count($rows) . ' pegawai).'
            );
        } catch (\Throwable $e) {
            error_log('TransferPerjadinController::save error: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('transfer-perjadin/atur/' . $suratTugasId), 'error', 'Gagal menyimpan konfigurasi transfer.');
        }
    }

    // ──────────────────────────────────────────────────────────
    // EXPORT
    // ──────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAdmin();

        $ids = $this->model->getConfiguredSuratTugasIds();
        if ($ids === []) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Belum ada Surat Tugas yang dikonfigurasi transfernya.');
            return;
        }

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : null;

        $stRows = [];
        foreach ($ids as $id) {
            $data = $this->assembleSt($id);
            if ($data === null) {
                continue;
            }
            if ($tahun !== null) {
                $mulai = (string) ($data['st']['tanggal_mulai'] ?? '');
                if ((int) date('Y', strtotime($mulai)) !== $tahun) {
                    continue;
                }
            }
            $stRows[] = $data;
        }

        if ($stRows === []) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Tidak ada data transfer pada filter tahun tersebut.');
            return;
        }

        $suffix = $tahun !== null ? '_' . $tahun : '';
        (new TransferPerjadinExportService())->download($stRows, 'Transfer_Perjalanan_Dinas' . $suffix . '_' . date('Ymd_His') . '.xlsx');
    }

    // ──────────────────────────────────────────────────────────
    // DATA ASSEMBLY
    // ──────────────────────────────────────────────────────────

    /**
     * Susun data satu Surat Tugas: header ST + daftar pegawai dengan
     * penerimaan (dari transaksi perjalanan dinas), potongan, dan status transfer.
     */
    private function assembleSt(int $suratTugasId): ?array
    {
        $pdo = \DatabaseSuratTugas::getConnection();
        if ($pdo === null) {
            return null;
        }

        $st = $this->fetchSt($pdo, $suratTugasId);
        if ($st === null) {
            return null;
        }

        $pekerja = $this->fetchPegawai($pdo, $suratTugasId);
        $config = $this->model->getBySuratTugasId($suratTugasId);
        $trxRows = $this->model->getTransaksiPerjadinBySuratTugas($suratTugasId, (string) $st['nomor_surat']);

        $pegawaiList = [];
        foreach ($pekerja as $p) {
            $nip = trim((string) $p['nip']);
            $nama = trim((string) $p['nama']);
            $lines = TransferPerjadinExportService::matchLinesForPegawai($trxRows, $nip, $nama);

            $penerimaan = 0.0;
            foreach ($lines as $l) {
                $penerimaan += (float) ($l['nilai'] ?? 0);
            }

            $cfg = $config[$nip] ?? null;
            $potongan = (float) ($cfg['potongan'] ?? 0);
            $isKe = (int) ($cfg['is_transfer_ke'] ?? 0) === 1;

            $pegawaiList[] = [
                'nip' => $nip,
                'nama' => $nama,
                'pangkat' => $p['pangkat'] ?? '',
                'jabatan' => $p['jabatan'] ?? '',
                'penerimaan' => $penerimaan,
                'potongan' => $potongan,
                'is_transfer_ke' => $isKe,
                'bersih' => $penerimaan - $potongan,
                'lines' => $lines,
            ];
        }

        return ['st' => $st, 'pegawai' => $pegawaiList];
    }

    private function fetchSt(\PDO $pdo, int $suratTugasId): ?array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT id, nomor_surat, tanggal_surat, untuk,
                       tanggal_mulai, tanggal_selesai
                FROM surat_tugas WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$suratTugasId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('TransferPerjadinController::fetchSt error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Daftar pegawai dalam satu Surat Tugas (unik per NIP).
     *
     * @return array<int, array>
     */
    private function fetchPegawai(\PDO $pdo, int $suratTugasId): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    pt.nip                                 AS pt_nip,
                    COALESCE(p.nip, pt.nip)                AS nip,
                    COALESCE(p.nama, pt.nip, 'Pegawai')    AS nama,
                    p.pangkat,
                    p.jabatan,
                    pt.urutan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas = ?
                ORDER BY pt.urutan ASC, p.nama ASC
            ");
            $stmt->execute([$suratTugasId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $seen = [];
            $out = [];
            foreach ($rows as $r) {
                $nip = trim((string) $r['nip']);
                if ($nip === '' || isset($seen[$nip])) {
                    continue;
                }
                $seen[$nip] = true;
                $out[] = $r;
            }
            return $out;
        } catch (\Throwable $e) {
            error_log('TransferPerjadinController::fetchPegawai error: ' . $e->getMessage());
            return [];
        }
    }

    private function parseRupiah($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        // Terima format "1.500.000", "1500000", "1,5" -> angka.
        $s = preg_replace('/[^0-9,.\-]/', '', $s) ?? '';
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.')) {
            // Titik sebagai pemisah ribuan bila grup 3 digit.
            if (preg_match('/\.\d{3}(\D|$)/', $s)) {
                $s = str_replace('.', '', $s);
            }
        }
        return max(0.0, (float) $s);
    }

    private function redirectWithMessage(string $url, string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }
}
