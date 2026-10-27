<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\PengajuanTu;
use App\Models\KasBank;

/**
 * Controller modul Tambah Uang (TU) — dikelola penuh oleh admin/bendahara.
 *
 * Routes:
 *   GET  /pengajuan-tu                   → index()            : daftar semua TU
 *   GET  /pengajuan-tu/create            → create()           : form input TU
 *   POST /pengajuan-tu/store             → store()            : simpan TU baru
 *   GET  /pengajuan-tu/edit/{id}         → edit()             : form edit TU
 *   POST /pengajuan-tu/update/{id}       → update()           : simpan edit TU
 *   POST /pengajuan-tu/delete/{id}       → delete()           : hapus TU (diajukan)
 *   GET  /pengajuan-tu/show/{id}         → show()             : detail + pembayaran
 *   POST /pengajuan-tu/cairkan/{id}      → cairkan()          : input SP2D, catat kas
 *   POST /pengajuan-tu/bayar/{id}        → bayar()            : catat pembayaran dari TU
 *   POST /pengajuan-tu/hapus-pembayaran/{id}/{t} → hapusPembayaran()
 *   POST /pengajuan-tu/selesaikan/{id}   → selesaikan()       : input setoran sisa + tutup
 */
class PengajuanTuController
{
    private PengajuanTu $model;
    private KasBank     $kasBank;

    public function __construct(PengajuanTu $model, KasBank $kasBank)
    {
        $this->model   = $model;
        $this->kasBank = $kasBank;
    }

    // ── Guard ──────────────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function adminId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    // ── INDEX ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAdmin();

        $status  = trim($_GET['status']  ?? '');
        $tahun   = isset($_GET['tahun'])   && $_GET['tahun']   !== '' ? (int) $_GET['tahun']   : (int) date('Y');
        $bulan   = isset($_GET['bulan'])   && $_GET['bulan']   !== '' ? (int) $_GET['bulan']   : null;
        $seksiId = isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null;

        $allowedStatus = ['', 'diajukan', 'disetujui', 'cair', 'selesai', 'ditolak'];
        if (!in_array($status, $allowedStatus, true)) $status = '';

        $perPage = 20;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $total   = $this->model->countAll($status ?: null, $seksiId, $tahun, $bulan);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $items = $this->model->getAll($status ?: null, $seksiId, $tahun, $bulan, $perPage, $offset);
        $stats = $this->model->getStats($tahun);

        // Daftar seksi untuk filter dropdown
        $db = \Database::getConnection();
        $seksiList = $db->query("SELECT id, kode_seksi, nama_seksi FROM seksi ORDER BY kode_seksi ASC")
                        ->fetchAll(\PDO::FETCH_ASSOC);

        $pagination = [
            'page' => $page, 'perPage' => $perPage,
            'total' => $total, 'totalPages' => $totalPages,
            'baseUrl' => base_url('pengajuan-tu'),
        ];

        $pageTitle  = 'Tambah Uang (TU)';
        $activePage = 'pengajuan_tu';
        $viewFile   = __DIR__ . '/../../views/pengajuan_tu/index.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ── CREATE / STORE ────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireAdmin();

        $programs = $this->getPrograms();
        $errors   = [];
        $form     = [];

        $pageTitle  = 'Input Pengajuan TU';
        $activePage = 'pengajuan_tu';
        $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
        include __DIR__ . '/../../views/layout.php';
    }

    public function store(): void
    {
        $this->requireAdmin();

        $form = [
            'tanggal_pengajuan' => trim($_POST['tanggal_pengajuan'] ?? date('Y-m-d')),
            'bulan'             => (int) ($_POST['bulan'] ?? date('n')),
            'tahun'             => (int) ($_POST['tahun'] ?? date('Y')),
            'kebutuhan_raw'     => $_POST['kebutuhan'] ?? '0',
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
            'rekening_id'       => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : null,
            'program_id'        => $_POST['program_id']      ?? '',
            'kegiatan_id'       => $_POST['kegiatan_id']     ?? '',
            'sub_kegiatan_id'   => $_POST['sub_kegiatan_id'] ?? '',
        ];

        $kebutuhan = $this->parseNominal($form['kebutuhan_raw']);

        $errors = [];
        if (!$form['tanggal_pengajuan'] || !strtotime($form['tanggal_pengajuan'])) $errors[] = 'Tanggal pengajuan tidak valid.';
        if ($form['bulan'] < 1 || $form['bulan'] > 12)      $errors[] = 'Bulan tidak valid.';
        if ($form['tahun'] < 2020 || $form['tahun'] > 2099) $errors[] = 'Tahun tidak valid.';
        if ($kebutuhan <= 0)               $errors[] = 'Kebutuhan TU (Rp) wajib diisi dan harus lebih dari 0.';
        if ($form['keperluan'] === '')     $errors[] = 'Keperluan / uraian kegiatan wajib diisi.';
        if (empty($form['rekening_id']))   $errors[] = 'Rekening belanja wajib dipilih.';

        $seksiId = $form['rekening_id'] ? $this->seksiIdFromRekening((int) $form['rekening_id']) : null;
        if ($form['rekening_id'] && $seksiId === null) {
            $errors[] = 'Rekening yang dipilih tidak memiliki seksi yang valid.';
        }

        if (!empty($errors)) {
            $programs = $this->getPrograms();
            $pageTitle  = 'Input Pengajuan TU';
            $activePage = 'pengajuan_tu';
            $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
            include __DIR__ . '/../../views/layout.php';
            return;
        }

        $nomorPengajuan = $this->model->generateNomor((int) $seksiId, $form['bulan'], $form['tahun']);

        try {
            $id = $this->model->create(
                $nomorPengajuan,
                $form['tanggal_pengajuan'],
                (int) $seksiId,
                $form['tahun'],
                $form['bulan'],
                $kebutuhan,
                $form['keperluan'],
                $this->adminId(),
                $form['rekening_id']
            );
            $this->logAudit($this->adminId(), 'tu_create', 'pengajuan_tu', $id,
                'Input TU ' . $nomorPengajuan);
            $this->flash('success', 'Pengajuan TU berhasil dibuat. Silakan cairkan SP2D-TU saat dana masuk.');
            $this->redirect(base_url('pengajuan-tu/show/' . $id));
        } catch (\Throwable $e) {
            error_log('PengajuanTuController::store error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
            $programs = $this->getPrograms();
            $pageTitle  = 'Input Pengajuan TU';
            $activePage = 'pengajuan_tu';
            $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
            include __DIR__ . '/../../views/layout.php';
        }
    }

    // ── EDIT / UPDATE / DELETE ────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $tu = $this->model->getById($id);
        if (!$tu || $tu['status'] !== 'diajukan') {
            $this->flash('error', 'Pengajuan TU tidak dapat diedit.');
            $this->redirect(base_url('pengajuan-tu'));
        }

        $programs = $this->getPrograms();
        $errors   = [];
        $form     = $tu;

        $pageTitle  = 'Edit Pengajuan TU';
        $activePage = 'pengajuan_tu';
        $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
        include __DIR__ . '/../../views/layout.php';
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $tu = $this->model->getById($id);
        if (!$tu || $tu['status'] !== 'diajukan') {
            $this->flash('error', 'Pengajuan TU tidak dapat diedit.');
            $this->redirect(base_url('pengajuan-tu'));
        }

        $form = [
            'tanggal_pengajuan' => trim($_POST['tanggal_pengajuan'] ?? ''),
            'kebutuhan_raw'     => $_POST['kebutuhan'] ?? '0',
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
            'rekening_id'       => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : null,
            'program_id'        => $_POST['program_id']      ?? '',
            'kegiatan_id'       => $_POST['kegiatan_id']     ?? '',
            'sub_kegiatan_id'   => $_POST['sub_kegiatan_id'] ?? '',
        ];
        $kebutuhan = $this->parseNominal($form['kebutuhan_raw']);

        $errors = [];
        if (!$form['tanggal_pengajuan'] || !strtotime($form['tanggal_pengajuan'])) $errors[] = 'Tanggal tidak valid.';
        if ($kebutuhan <= 0) $errors[] = 'Kebutuhan (Rp) harus lebih dari 0.';
        if ($form['keperluan'] === '') $errors[] = 'Keperluan wajib diisi.';
        if (empty($form['rekening_id'])) $errors[] = 'Rekening belanja wajib dipilih.';

        if ($form['rekening_id']) {
            $seksiId = $this->seksiIdFromRekening((int) $form['rekening_id']);
            if ($seksiId === null || $seksiId !== (int) $tu['seksi_id']) {
                $errors[] = 'Rekening harus berasal dari seksi yang sama dengan pengajuan TU ini.';
            }
        }

        if (!empty($errors)) {
            $form     = array_merge($tu, $form);
            $programs = $this->getPrograms();
            $pageTitle  = 'Edit Pengajuan TU';
            $activePage = 'pengajuan_tu';
            $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
            include __DIR__ . '/../../views/layout.php';
            return;
        }

        $ok = $this->model->update($id, $kebutuhan, $form['keperluan'], $form['tanggal_pengajuan'], $form['rekening_id']);
        $this->logAudit($this->adminId(), 'tu_update', 'pengajuan_tu', $id, 'Update TU id ' . $id);
        $this->flash($ok ? 'success' : 'error', $ok ? 'Pengajuan TU diperbarui.' : 'Gagal memperbarui.');
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        $ok = $this->model->delete($id);
        $this->logAudit($this->adminId(), 'tu_delete', 'pengajuan_tu', $id, 'Hapus TU id ' . $id);
        $this->flash($ok ? 'success' : 'error',
            $ok ? 'Pengajuan TU dihapus.' : 'Tidak dapat dihapus — hanya pengajuan berstatus "diajukan" yang bisa dihapus.');
        $this->redirect(base_url('pengajuan-tu'));
    }

    // ── SHOW / DETAIL ─────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requireAdmin();

        $data = $this->model->getByIdWithTransaksi($id);
        if (!$data) {
            $this->flash('error', 'Pengajuan TU tidak ditemukan.');
            $this->redirect(base_url('pengajuan-tu'));
        }

        $programs = $this->getPrograms();

        $pageTitle  = 'Detail TU — ' . $data['nomor_pengajuan'];
        $activePage = 'pengajuan_tu';
        $viewFile   = __DIR__ . '/../../views/pengajuan_tu/show.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ── CAIRKAN ───────────────────────────────────────────────────────────

    public function cairkan(int $id): void
    {
        $this->requireAdmin();

        $nomorSp2d   = trim($_POST['nomor_sp2d']   ?? '');
        $tanggalCair = trim($_POST['tanggal_cair']  ?? '');
        $nominalCair = $this->parseNominal($_POST['nominal_cair'] ?? '0');
        $batasSpj    = trim($_POST['batas_spj'] ?? '') ?: null;

        if (!$nomorSp2d || !$tanggalCair || !strtotime($tanggalCair) || $nominalCair <= 0) {
            $this->flash('error', 'Nomor SP2D, tanggal cair, dan nominal wajib diisi dengan benar.');
            $this->redirect(base_url('pengajuan-tu/show/' . $id));
        }
        if ($batasSpj && !strtotime($batasSpj)) {
            $this->flash('error', 'Format tanggal batas SPJ tidak valid.');
            $this->redirect(base_url('pengajuan-tu/show/' . $id));
        }

        try {
            $kasBankId = $this->model->cairkan($id, $nomorSp2d, $tanggalCair, $nominalCair, $batasSpj, $this->adminId());
            $this->logAudit($this->adminId(), 'tu_cairkan', 'pengajuan_tu', $id,
                sprintf('SP2D-TU %s cair Rp%s (kas_bank_id=%d)', $nomorSp2d, number_format($nominalCair, 0, ',', '.'), $kasBankId));
            $this->flash('success', 'SP2D-TU berhasil dicairkan dan dicatat ke Kas & Bank.');
        } catch (\Throwable $e) {
            error_log('cairkan TU error: ' . $e->getMessage());
            $this->flash('error', 'Gagal mencairkan TU: ' . $e->getMessage());
        }
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── BAYAR (REALISASI) ─────────────────────────────────────────────────

    public function bayar(int $id): void
    {
        $this->requireAdmin();

        $data = [
            'tanggal'        => trim($_POST['tanggal'] ?? date('Y-m-d')),
            'rekening_id'    => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : 0,
            'uraian'         => trim($_POST['uraian'] ?? ''),
            'nama_penerima'  => trim($_POST['nama_penerima'] ?? ''),
            'jenis_transaksi'=> trim($_POST['jenis_transaksi'] ?? 'belanja'),
            'nilai'          => $this->parseNominal($_POST['nilai'] ?? '0'),
            'nomor_bukti'    => trim($_POST['nomor_bukti'] ?? ''),
        ];

        try {
            $trxId = $this->model->catatPembayaran($id, $data, $this->adminId());
            $this->logAudit($this->adminId(), 'tu_bayar', 'pengajuan_tu', $id,
                sprintf('Pembayaran transaksi_id=%d Rp%s', $trxId, number_format($data['nilai'], 0, ',', '.')));
            $this->flash('success', 'Pembayaran berhasil dicatat (Rp ' . number_format($data['nilai'], 0, ',', '.') . ').');
        } catch (\Throwable $e) {
            error_log('bayar TU error: ' . $e->getMessage());
            $this->flash('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
        }
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    public function hapusPembayaran(int $id, int $transaksiId): void
    {
        $this->requireAdmin();

        try {
            $ok = $this->model->hapusPembayaran($id, $transaksiId);
            $this->logAudit($this->adminId(), 'tu_hapus_bayar', 'pengajuan_tu', $id,
                'Hapus pembayaran transaksi_id ' . $transaksiId);
            $this->flash($ok ? 'success' : 'error',
                $ok ? 'Pembayaran dihapus.' : 'Gagal menghapus pembayaran.');
        } catch (\Throwable $e) {
            error_log('hapus pembayaran TU error: ' . $e->getMessage());
            $this->flash('error', 'Gagal menghapus pembayaran: ' . $e->getMessage());
        }
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── SELESAIKAN ────────────────────────────────────────────────────────

    public function selesaikan(int $id): void
    {
        $this->requireAdmin();

        $nominalSetor = $this->parseNominal($_POST['nominal_setor'] ?? '0');
        $tanggalSetor = trim($_POST['tanggal_setoran'] ?? date('Y-m-d'));
        $nomorBukti   = trim($_POST['nomor_bukti_setoran'] ?? '');
        $catatan      = trim($_POST['catatan_admin'] ?? '');

        try {
            $result = $this->model->selesaikan(
                $id,
                $this->adminId(),
                $nominalSetor,
                $tanggalSetor,
                $nomorBukti ?: null,
                $catatan ?: null
            );
            $msg = 'Siklus TU diselesaikan.';
            if ($result['disetor'] > 0) {
                $msg .= sprintf(' Setoran sisa Rp %s dicatat ke kas.', number_format($result['disetor'], 0, ',', '.'));
            } else {
                $msg .= ' Tidak ada sisa yang disetor.';
            }
            $this->logAudit($this->adminId(), 'tu_selesai', 'pengajuan_tu', $id,
                'Selesaikan TU id ' . $id . ', setor Rp ' . number_format($result['disetor'], 0, ',', '.'));
            $this->flash('success', $msg);
        } catch (\Throwable $e) {
            error_log('selesaikan TU error: ' . $e->getMessage());
            $this->flash('error', 'Gagal menyelesaikan TU: ' . $e->getMessage());
        }
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── HELPERS ───────────────────────────────────────────────────────────

    private function parseNominal(mixed $raw): float
    {
        return (float) str_replace(['.', ','], ['', '.'], (string) $raw);
    }

    private function seksiIdFromRekening(int $rekeningId): ?int
    {
        $db = \Database::getConnection();
        $stmt = $db->prepare("
            SELECT sk.seksi_id
            FROM rekening r
            INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $rekeningId]);
        $seksiId = $stmt->fetchColumn();
        if ($seksiId === false || $seksiId === null || (int) $seksiId === 0) {
            return null;
        }
        return (int) $seksiId;
    }

    private function getPrograms(): array
    {
        $db = \Database::getConnection();
        return $db->query("SELECT id, kode_program, nama_program FROM program ORDER BY kode_program ASC")
                  ->fetchAll(\PDO::FETCH_ASSOC);
    }

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

    private function logAudit(int $userId, string $aksi, string $tabel, ?int $recordId, string $keterangan): void
    {
        try {
            $db   = \Database::getConnection();
            $stmt = $db->prepare('INSERT INTO audit_log (user_id, aksi, tabel, record_id, keterangan) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $aksi, $tabel, $recordId, $keterangan]);
        } catch (\Exception $e) {
            error_log('audit_log error: ' . $e->getMessage());
        }
    }
}
