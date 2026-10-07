<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\KasBank;
use App\Models\PengajuanGu;
use App\Models\Transaksi;

/**
 * Alur Pengajuan SPJ menjadi GU (admin/bendahara only).
 * Admin memilih sendiri transaksi yang belum di-SPJ-kan (bisa banyak
 * sekaligus) -> sistem membuat pengajuan + baris GU menunggu_cair otomatis.
 */
class PengajuanGuController
{
    private PengajuanGu $pengajuanModel;
    private KasBank $kasBankModel;

    public function __construct(PengajuanGu $pengajuanModel, KasBank $kasBankModel)
    {
        $this->pengajuanModel = $pengajuanModel;
        $this->kasBankModel = $kasBankModel;
    }

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['is_admin'])) {
            $_SESSION['flash_message'] = 'Akses ditolak: Fitur Pengajuan GU hanya untuk admin/bendahara';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    /**
     * Halaman Pengajuan SPJ -> GU: daftar siap diajukan + riwayat pengajuan.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        if ($bulan !== null && ($bulan < 1 || $bulan > 12)) {
            $bulan = null;
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) date('Y');
        }

        $siapList = $this->pengajuanModel->getSiapSpj($bulan, $tahun);
        $totalSiap = 0.0;
        foreach ($siapList as $row) {
            $totalSiap += (float) ($row['nilai'] ?? 0);
        }
        $riwayat = $this->pengajuanModel->getAll();
        $totalBelumSpj = $this->pengajuanModel->getTotalBelumSpj();

        // Item per pengajuan untuk tampilan rincian (riwayat biasanya sedikit)
        $itemsMap = [];
        foreach ($riwayat as $p) {
            $itemsMap[(int) $p['id']] = $this->pengajuanModel->getItems((int) $p['id']);
        }

        $pageTitle = 'Pengajuan SPJ ke GU';
        $activePage = 'pengajuan_gu';
        $viewFile = __DIR__ . '/../../views/pengajuan_gu/index.php';

        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Buat pengajuan SPJ dari transaksi pilihan.
     */
    public function store(): void
    {
        $this->requireAdmin();

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            $this->redirectWithMessage(base_url('pengajuan-gu'), 'error', 'Pilih minimal satu transaksi untuk diajukan.');
            return;
        }

        $tanggal = trim($_POST['tanggal_pengajuan'] ?? date('Y-m-d'));
        if (Transaksi::normalizeTanggalLunas($tanggal) === null) {
            $this->redirectWithMessage(base_url('pengajuan-gu'), 'error', 'Tanggal pengajuan tidak valid.');
            return;
        }
        $keterangan = trim($_POST['keterangan'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 1);

        try {
            $idBaru = $this->pengajuanModel->create($ids, $tanggal, $keterangan, $userId, $this->kasBankModel);
            $pengajuan = $this->pengajuanModel->getById($idBaru);
            $this->redirectWithMessage(
                base_url('pengajuan-gu'),
                'success',
                'Pengajuan ' . ($pengajuan['nomor_pengajuan'] ?? ('#' . $idBaru)) . ' berhasil dibuat (Rp ' . number_format((float) ($pengajuan['total_nominal'] ?? 0), 0, ',', '.') . ') dan tercatat sebagai GU menunggu cair.'
            );
        } catch (\Throwable $e) {
            $this->redirectWithMessage(base_url('pengajuan-gu'), 'error', 'Gagal membuat pengajuan: ' . $e->getMessage());
        }
    }

    /**
     * Tandai pengajuan sudah cair (SP2D masuk ke kas).
     */
    public function cairkan(int $id): void
    {
        $this->requireAdmin();

        $tanggalCair = trim($_POST['tanggal_cair'] ?? date('Y-m-d'));
        if (Transaksi::normalizeTanggalLunas($tanggalCair) === null) {
            $tanggalCair = date('Y-m-d');
        }
        $ok = $this->pengajuanModel->tandaiCair($id, $tanggalCair, $this->kasBankModel);
        $this->redirectWithMessage(
            base_url('pengajuan-gu'),
            $ok ? 'success' : 'error',
            $ok ? 'Pengajuan berhasil ditandai cair, saldo kas bertambah.' : 'Gagal (pengajuan tidak ditemukan / sudah cair / dibatalkan).'
        );
    }

    /**
     * Batalkan pengajuan yang masih diajukan (lepas transaksi + hapus GU).
     */
    public function batalkan(int $id): void
    {
        $this->requireAdmin();

        $ok = $this->pengajuanModel->batalkan($id, $this->kasBankModel);
        $this->redirectWithMessage(
            base_url('pengajuan-gu'),
            $ok ? 'success' : 'error',
            $ok ? 'Pengajuan dibatalkan, transaksi kembali siap di-SPJ-kan.' : 'Gagal (pengajuan sudah cair / tidak ditemukan).'
        );
    }

    /**
     * Lepas satu transaksi dari pengajuan yang masih diajukan.
     */
    public function lepas(int $pengajuanId, int $trxId): void
    {
        $this->requireAdmin();

        $ok = $this->pengajuanModel->lepasTransaksi($pengajuanId, $trxId, $this->kasBankModel);
        $this->redirectWithMessage(
            base_url('pengajuan-gu'),
            $ok ? 'success' : 'error',
            $ok ? 'Transaksi dilepas dari pengajuan.' : 'Gagal melepas (pengajuan sudah cair / tidak ditemukan).'
        );
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
