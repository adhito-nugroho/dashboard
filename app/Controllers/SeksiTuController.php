<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\PengajuanTu;

/**
 * Controller modul TU sisi SEKSI (RLPM / TKUK / Subbag TU).
 *
 * Routes (prefix /seksi/tu):
 *   GET  /seksi/tu              → index()   : daftar TU milik seksi ini
 *   GET  /seksi/tu/create       → create()  : form ajukan TU baru
 *   POST /seksi/tu/store        → store()   : simpan pengajuan TU
 *   GET  /seksi/tu/edit/{id}    → edit()    : form edit (hanya status diajukan)
 *   POST /seksi/tu/update/{id}  → update()  : simpan edit
 *   POST /seksi/tu/delete/{id}  → delete()  : hapus (hanya status diajukan)
 */
class SeksiTuController
{
    private PengajuanTu $model;

    public function __construct(PengajuanTu $model)
    {
        $this->model = $model;
    }

    // ── Guard ──────────────────────────────────────────────────────────────

    private function requireSeksi(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['rlpm','tkuk','tu','seksi'], true)) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function seksiId(): int
    {
        return (int) ($_SESSION['seksi_id'] ?? 0);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    // ── INDEX ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireSeksi();
        $seksiId = $this->seksiId();

        $status = trim($_GET['status'] ?? '');
        $allowedStatus = ['', 'diajukan', 'disetujui', 'cair', 'selesai', 'ditolak'];
        if (!in_array($status, $allowedStatus, true)) $status = '';

        $items = $this->model->getBySeksi($seksiId, $status ?: null);
        $stats = $this->model->getStats((int) date('Y'));

        // TU aktif (cair) untuk ditampilkan di panel "Dana TU Aktif"
        $tuAktif = $this->model->getAktifBySeksi($seksiId);

        $pageTitle  = 'Tambah Uang (TU) Saya';
        $activePage = 'tu';
        $viewFile   = __DIR__ . '/../../views/seksi/tu_index.php';
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    // ── CREATE ─────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireSeksi();
        $errors = [];
        $form   = [];

        $pageTitle  = 'Ajukan Tambah Uang (TU)';
        $activePage = 'tu';
        $viewFile   = __DIR__ . '/../../views/seksi/tu_form.php';
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    // ── STORE ──────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireSeksi();
        $seksiId = $this->seksiId();
        $userId  = $this->userId();

        $form = [
            'tanggal_pengajuan' => trim($_POST['tanggal_pengajuan'] ?? date('Y-m-d')),
            'bulan'             => (int) ($_POST['bulan'] ?? date('n')),
            'tahun'             => (int) ($_POST['tahun'] ?? date('Y')),
            'kebutuhan_raw'     => $_POST['kebutuhan'] ?? '0',
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
        ];

        $kebutuhan = (float) str_replace(['.', ','], ['', '.'], $form['kebutuhan_raw']);

        $errors = [];
        if (!$form['tanggal_pengajuan'] || !strtotime($form['tanggal_pengajuan'])) $errors[] = 'Tanggal pengajuan tidak valid.';
        if ($form['bulan'] < 1 || $form['bulan'] > 12)     $errors[] = 'Bulan tidak valid.';
        if ($form['tahun'] < 2020 || $form['tahun'] > 2099) $errors[] = 'Tahun tidak valid.';
        if ($kebutuhan <= 0)  $errors[] = 'Kebutuhan TU (Rp) wajib diisi dan harus lebih dari 0.';
        if ($form['keperluan'] === '') $errors[] = 'Keperluan / uraian kegiatan wajib diisi.';

        if (!empty($errors)) {
            $pageTitle  = 'Ajukan Tambah Uang (TU)';
            $activePage = 'tu';
            $viewFile   = __DIR__ . '/../../views/seksi/tu_form.php';
            include __DIR__ . '/../../views/layout_seksi.php';
            return;
        }

        $nomorPengajuan = $this->model->generateNomor($seksiId, $form['bulan'], $form['tahun']);

        try {
            $id = $this->model->create(
                $nomorPengajuan,
                $form['tanggal_pengajuan'],
                $seksiId,
                $form['tahun'],
                $form['bulan'],
                $kebutuhan,
                $form['keperluan'],
                $userId
            );
            $this->flash('success', 'Pengajuan TU berhasil diajukan. Menunggu persetujuan admin.');
            $this->redirect(base_url('seksi/tu'));
        } catch (\Throwable $e) {
            error_log('SeksiTuController::store error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
            $pageTitle  = 'Ajukan Tambah Uang (TU)';
            $activePage = 'tu';
            $viewFile   = __DIR__ . '/../../views/seksi/tu_form.php';
            include __DIR__ . '/../../views/layout_seksi.php';
        }
    }

    // ── EDIT ───────────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requireSeksi();
        $tu = $this->model->getById($id);

        if (!$tu || (int) $tu['seksi_id'] !== $this->seksiId() || $tu['status'] !== 'diajukan') {
            $this->flash('error', 'Pengajuan TU tidak dapat diedit.');
            $this->redirect(base_url('seksi/tu'));
        }

        $errors = [];
        $form   = $tu;

        $pageTitle  = 'Edit Pengajuan TU';
        $activePage = 'tu';
        $viewFile   = __DIR__ . '/../../views/seksi/tu_form.php';
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    // ── UPDATE ─────────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->requireSeksi();
        $tu = $this->model->getById($id);

        if (!$tu || (int) $tu['seksi_id'] !== $this->seksiId() || $tu['status'] !== 'diajukan') {
            $this->flash('error', 'Pengajuan TU tidak dapat diedit.');
            $this->redirect(base_url('seksi/tu'));
        }

        $form = [
            'tanggal_pengajuan' => trim($_POST['tanggal_pengajuan'] ?? ''),
            'kebutuhan_raw'     => $_POST['kebutuhan'] ?? '0',
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
        ];
        $kebutuhan = (float) str_replace(['.', ','], ['', '.'], $form['kebutuhan_raw']);

        $errors = [];
        if (!$form['tanggal_pengajuan'] || !strtotime($form['tanggal_pengajuan'])) $errors[] = 'Tanggal tidak valid.';
        if ($kebutuhan <= 0) $errors[] = 'Kebutuhan (Rp) harus lebih dari 0.';
        if ($form['keperluan'] === '') $errors[] = 'Keperluan wajib diisi.';

        if (!empty($errors)) {
            $form = array_merge($tu, $form);
            $pageTitle  = 'Edit Pengajuan TU';
            $activePage = 'tu';
            $viewFile   = __DIR__ . '/../../views/seksi/tu_form.php';
            include __DIR__ . '/../../views/layout_seksi.php';
            return;
        }

        $ok = $this->model->update($id, $kebutuhan, $form['keperluan'], $form['tanggal_pengajuan']);
        $this->flash($ok ? 'success' : 'error', $ok ? 'Pengajuan TU diperbarui.' : 'Gagal memperbarui.');
        $this->redirect(base_url('seksi/tu'));
    }

    // ── DELETE ─────────────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->requireSeksi();
        $tu = $this->model->getById($id);

        if (!$tu || (int) $tu['seksi_id'] !== $this->seksiId()) {
            $this->flash('error', 'Pengajuan TU tidak ditemukan.');
            $this->redirect(base_url('seksi/tu'));
        }

        $ok = $this->model->delete($id);
        $this->flash($ok ? 'success' : 'error',
            $ok ? 'Pengajuan TU dihapus.' : 'Tidak dapat dihapus — hanya pengajuan berstatus "diajukan" yang bisa dihapus.');
        $this->redirect(base_url('seksi/tu'));
    }

    // ── HELPERS ───────────────────────────────────────────────────────────

    private function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
    }

    private function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
