<?php

namespace App\Controllers;

use App\Models\KalibrasiKuitansi;
use App\Models\Transaksi;
use App\Services\KuitansiPdfService;
use PDO;

class KuitansiController
{
    private KalibrasiKuitansi $kalibrasi;
    private Transaksi $transaksi;
    private KuitansiPdfService $pdf;

    public function __construct(PDO $db)
    {
        $this->kalibrasi = new KalibrasiKuitansi($db);
        $this->transaksi = new Transaksi($db);
        $this->pdf = new KuitansiPdfService();
    }

    private function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function requireAdmin(): void
    {
        $this->requireLogin();
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo 'Akses ditolak: halaman kalibrasi hanya untuk admin.';
            exit;
        }
    }

    private function render(array $vars = []): void
    {
        extract($vars);
        $pageTitle = $vars['pageTitle'] ?? 'Kalibrasi Cetak Kuitansi';
        $activePage = 'kalibrasi_kuitansi';
        $viewFile = __DIR__ . '/../../views/kuitansi/kalibrasi.php';
        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Halaman kalibrasi (admin saja): form offset_x/offset_y + Simpan + Cetak Uji.
     */
    public function kalibrasi(): void
    {
        $this->requireAdmin();
        $data = $this->kalibrasi->get();

        $flash = $_SESSION['flash_message'] ?? null;
        $flashType = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $this->render([
            'pageTitle' => 'Kalibrasi Cetak Kuitansi',
            'kalibrasi' => $data,
            'flash' => $flash,
            'flashType' => $flashType,
        ]);
    }

    /**
     * Simpan offset (update baris tunggal, bukan insert baru).
     */
    public function simpanKalibrasi(): void
    {
        $this->requireAdmin();
        $ox = isset($_POST['offset_x_mm']) ? (float) str_replace(',', '.', (string) $_POST['offset_x_mm']) : 0.0;
        $oy = isset($_POST['offset_y_mm']) ? (float) str_replace(',', '.', (string) $_POST['offset_y_mm']) : 0.0;
        $updatedBy = (string) ($_SESSION['username'] ?? ('user#' . ($_SESSION['user_id'] ?? '?')));

        $this->kalibrasi->save($ox, $oy, $updatedBy);

        $_SESSION['flash_message'] = sprintf('Offset kalibrasi tersimpan (x=%+.2f mm, y=%+.2f mm).', $ox, $oy);
        $_SESSION['flash_type'] = 'success';
        header('Location: ' . base_url('kuitansi/kalibrasi'));
        exit;
    }

    /**
     * Cetak Uji (admin saja): memakai offset dari query string (nilai yang sedang
     * diketik di form, belum tentu tersimpan) — ?ox=..&oy=..
     */
    public function cetakUji(): void
    {
        $this->requireAdmin();
        $ox = isset($_GET['ox']) ? (float) str_replace(',', '.', (string) $_GET['ox']) : 0.0;
        $oy = isset($_GET['oy']) ? (float) str_replace(',', '.', (string) $_GET['oy']) : 0.0;
        $ox = max(-50, min(50, $ox));
        $oy = max(-50, min(50, $oy));
        $this->pdf->streamTestPage($ox, $oy);
        exit;
    }

    /**
     * Cetak kuitansi satu transaksi (tombol download hijau di kolom Aksi).
     * Admin boleh cetak semua; role seksi hanya milik seksinya sendiri.
     * Alur verifikasi bendahara tidak diubah — hanya membaca data.
     */
    public function cetak(int $id): void
    {
        $this->requireLogin();

        $trx = $this->transaksi->getById($id);
        if ($trx === null) {
            http_response_code(404);
            echo 'Transaksi tidak ditemukan.';
            exit;
        }

        $isAdmin = !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            $role = $_SESSION['role'] ?? '';
            $allowedRoles = ['rlpm', 'tkuk', 'tu', 'seksi'];
            if (!in_array($role, $allowedRoles, true)) {
                http_response_code(403);
                echo 'Akses ditolak.';
                exit;
            }
            $mySeksi = (int) ($_SESSION['seksi_id'] ?? 0);
            if ($mySeksi > 0 && (int) ($trx['seksi_id'] ?? 0) !== $mySeksi) {
                http_response_code(403);
                echo 'Akses ditolak: bukan transaksi seksi Anda.';
                exit;
            }
        }

        $kal = $this->kalibrasi->get();
        $this->pdf->streamKuitansi($trx, (float) $kal['offset_x_mm'], (float) $kal['offset_y_mm']);
        exit;
    }
}
