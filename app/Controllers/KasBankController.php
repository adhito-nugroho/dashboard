<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\KasBank;
use App\Models\Transaksi;

class KasBankController
{
    private KasBank $kasBankModel;
    private Transaksi $transaksiModel;

    public function __construct(KasBank $kasBankModel, Transaksi $transaksiModel)
    {
        $this->kasBankModel = $kasBankModel;
        $this->transaksiModel = $transaksiModel;
    }

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['is_admin'])) {
            $_SESSION['flash_message'] = 'Akses ditolak: Fitur Kas & Bank hanya untuk admin/bendahara';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    /**
     * Halaman Buku Pembantu Kas & Rekapitulasi UP/GU
     */
    public function index(): void
    {
        $this->requireAdmin();

        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : (int) date('m');
        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');

        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) date('m');
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) date('Y');
        }

        $ringkasan = $this->kasBankModel->getRingkasan($bulan, $tahun);
        $mutasiList = $this->kasBankModel->getByPeriode($bulan, $tahun);

        // Ambil juga daftar transaksi belanja diverifikasi di bulan ini untuk rincian mutasi kas keluar
        $db = \Database::getConnection();
        $stmtTrx = $db->prepare("
            SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar, t.nomor_bukti, t.uraian, t.nama_penerima, t.nilai, s.nama_seksi
            FROM transaksi t
            INNER JOIN seksi s ON t.seksi_id = s.id
            WHERE t.status = 'diverifikasi'
              AND MONTH(t.tanggal) = :bulan
              AND YEAR(t.tanggal) = :tahun
            ORDER BY t.nomor_bukti ASC, t.id ASC
        ");
        $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
        $belanjaList = $stmtTrx->fetchAll(\PDO::FETCH_ASSOC);

        $pageTitle = 'Kas & Bank (UP/GU)';
        $activePage = 'kas_bank';
        $viewFile = __DIR__ . '/../../views/kas_bank/index.php';

        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Simpan mutasi penerimaan kas baru
     */
    public function store(): void
    {
        $this->requireAdmin();

        $tanggal    = trim($_POST['tanggal'] ?? '');
        $jenis      = trim($_POST['jenis'] ?? 'gu');
        $nomorBukti = trim($_POST['nomor_bukti'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $nominalRaw = str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
        $nominal    = (float) $nominalRaw;
        $userId     = (int) ($_SESSION['user_id'] ?? 1);

        if (empty($tanggal) || !strtotime($tanggal)) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Tanggal mutasi tidak valid');
            return;
        }

        if (empty($keterangan)) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Keterangan mutasi wajib diisi');
            return;
        }

        if ($nominal <= 0) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Nominal mutasi harus lebih besar dari 0');
            return;
        }

        try {
            $this->kasBankModel->create($tanggal, $jenis, $nomorBukti, $keterangan, $nominal, $userId);
            $this->redirectWithMessage(base_url('kas-bank?bulan=' . date('n', strtotime($tanggal)) . '&tahun=' . date('Y', strtotime($tanggal))), 'success', 'Mutasi kas berhasil disimpan');
        } catch (\Throwable $e) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Gagal menyimpan mutasi kas: ' . $e->getMessage());
        }
    }

    /**
     * Update catatan mutasi kas
     */
    public function update(int $id): void
    {
        $this->requireAdmin();

        $tanggal    = trim($_POST['tanggal'] ?? '');
        $jenis      = trim($_POST['jenis'] ?? 'gu');
        $nomorBukti = trim($_POST['nomor_bukti'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $nominalRaw = str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
        $nominal    = (float) $nominalRaw;

        if (empty($tanggal) || empty($keterangan) || $nominal <= 0) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Data mutasi tidak lengkap');
            return;
        }

        $ok = $this->kasBankModel->update($id, $tanggal, $jenis, $nomorBukti, $keterangan, $nominal);
        $this->redirectWithMessage(
            base_url('kas-bank?bulan=' . date('n', strtotime($tanggal)) . '&tahun=' . date('Y', strtotime($tanggal))),
            $ok ? 'success' : 'error',
            $ok ? 'Mutasi kas berhasil diperbarui' : 'Gagal memperbarui mutasi kas'
        );
    }

    /**
     * Hapus mutasi kas
     */
    public function delete(int $id): void
    {
        $this->requireAdmin();

        $record = $this->kasBankModel->getById($id);
        if (!$record) {
            $this->redirectWithMessage(base_url('kas-bank'), 'error', 'Data mutasi tidak ditemukan');
            return;
        }

        $bulan = $record['bulan'];
        $tahun = $record['tahun'];
        $ok = $this->kasBankModel->delete($id);

        $this->redirectWithMessage(
            base_url("kas-bank?bulan={$bulan}&tahun={$tahun}"),
            $ok ? 'success' : 'error',
            $ok ? 'Mutasi kas berhasil dihapus' : 'Gagal menghapus mutasi kas'
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
