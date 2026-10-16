<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/database_surat_tugas.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\Seksi;
use App\Models\TransferPerjadin;
use App\Services\TransferPerjadinExportService;

/**
 * Modul Transfer Perjalanan Dinas (admin/bendahara only) — berbasis TRANSAKSI.
 *
 * Alur:
 *   /transfer-perjadin          → index()  : daftar transaksi perjalanan dinas +
 *                                            input Transfer Ke & Potongan (massal)
 *   /transfer-perjadin/save     → save()   : simpan konfigurasi (POST)
 *   /transfer-perjadin/export   → export() : unduh Excel (Detail + Rekap pivot)
 *
 * Penerimaan (gross) = transaksi.nilai. Penerimaan bersih = nilai - potongan.
 */
class TransferPerjadinController
{
    private TransferPerjadin $model;
    private Seksi $seksiModel;

    public function __construct(TransferPerjadin $model, Seksi $seksiModel)
    {
        $this->model = $model;
        $this->seksiModel = $seksiModel;
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

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $seksiId = isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null;
        $q = trim((string) ($_GET['q'] ?? ''));

        $transaksiList = $this->model->getTransaksiPerjadin($tahun, $bulan, $seksiId, $q);
        $configMap = $this->model->getConfigMap();
        $namaSuggestions = $this->model->getTransferKeSuggestions();
        $seksiList = $this->seksiModel->getAll();

        $pageTitle = 'Transfer Perjalanan Dinas';
        $activePage = 'transfer_perjadin';
        $viewFile = __DIR__ . '/../../views/transfer_perjadin/index.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // SAVE
    // ──────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->requireAdmin();

        $transferKeInput = $_POST['transfer_ke'] ?? [];
        $potonganInput = $_POST['potongan'] ?? [];
        if (!is_array($transferKeInput)) {
            $transferKeInput = [];
        }
        if (!is_array($potonganInput)) {
            $potonganInput = [];
        }

        $ids = array_unique(array_merge(array_keys($transferKeInput), array_keys($potonganInput)));
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $rows = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $rows[$id] = [
                'transfer_ke' => trim((string) ($transferKeInput[$id] ?? '')),
                'potongan' => $this->parseRupiah($potonganInput[$id] ?? 0),
            ];
        }

        try {
            $saved = $this->model->saveBulk($rows, $userId);
            $this->redirectWithMessage(
                base_url('transfer-perjadin'),
                'success',
                'Konfigurasi transfer tersimpan (' . $saved . ' transaksi dikonfigurasi).'
            );
        } catch (\Throwable $e) {
            error_log('TransferPerjadinController::save error: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Gagal menyimpan konfigurasi transfer.');
        }
    }

    // ──────────────────────────────────────────────────────────
    // EXPORT
    // ──────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAdmin();

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : null;

        $configured = $this->model->getConfiguredTransaksi();
        $rows = [];
        foreach ($configured as $c) {
            if ($tahun !== null) {
                $mulai = (string) ($c['tanggal'] ?? '');
                if ((int) date('Y', strtotime($mulai)) !== $tahun) {
                    continue;
                }
            }
            $rows[] = [
                'uraian' => (string) ($c['uraian'] ?? ''),
                'nama' => (string) ($c['nama_penerima'] ?? ''),
                'nilai' => (float) ($c['nilai'] ?? 0),
                'transfer_ke' => (string) ($c['transfer_ke'] ?? ''),
                'potongan' => (float) ($c['potongan'] ?? 0),
            ];
        }

        if ($rows === []) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Belum ada transaksi yang dikonfigurasi transfernya.');
            return;
        }

        $suffix = $tahun !== null ? '_' . $tahun : '';
        (new TransferPerjadinExportService())->download($rows, 'Transfer_Perjalanan_Dinas' . $suffix . '_' . date('Ymd_His') . '.xlsx');
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────

    private function parseRupiah($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $s = preg_replace('/[^0-9,.\-]/', '', $s) ?? '';
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.')) {
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
