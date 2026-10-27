<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\PengajuanTu;
use App\Models\KasBank;

/**
 * Controller modul Tambah Uang (TU) — khusus admin/bendahara.
 *
 * Routes:
 *   GET  /pengajuan-tu                  → index()       : daftar semua TU
 *   GET  /pengajuan-tu/show/{id}        → show()        : detail + transaksi
 *   POST /pengajuan-tu/setujui/{id}     → setujui()     : admin setujui
 *   POST /pengajuan-tu/tolak/{id}       → tolak()       : admin tolak
 *   POST /pengajuan-tu/cairkan/{id}     → cairkan()     : input SP2D, catat kas
 *   POST /pengajuan-tu/selesaikan/{id}  → selesaikan()  : tutup siklus + setoran sisa
 *   POST /pengajuan-tu/kaitkan/{id}     → kaitkan()     : hubungkan transaksi ke TU
 *   POST /pengajuan-tu/lepas/{id}/{t}   → lepas()       : putus kaitan transaksi
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

    // ── SHOW / DETAIL ──────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $this->requireAdmin();

        $data = $this->model->getByIdWithTransaksi($id);
        if (!$data) {
            $this->flash('error', 'Pengajuan TU tidak ditemukan.');
            $this->redirect(base_url('pengajuan-tu'));
        }

        // Transaksi diverifikasi milik seksi ini yang belum dikaitkan ke TU manapun
        // (untuk form "kaitkan ke TU ini")
        $db = \Database::getConnection();
        $stmtSiap = $db->prepare("
            SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar, t.nilai, t.uraian,
                   t.nomor_bukti, t.nama_penerima, r.nama_rekening
            FROM transaksi t
            INNER JOIN rekening r ON t.rekening_id = r.id
            WHERE t.seksi_id = :sid
              AND t.status = 'diverifikasi'
              AND t.sumber_dana = 'UP'
              AND t.pengajuan_tu_id IS NULL
              AND t.pengajuan_gu_id IS NULL
            ORDER BY COALESCE(t.tanggal_lunas_dibayar, t.tanggal) DESC
            LIMIT 100
        ");
        $stmtSiap->execute([':sid' => $data['seksi_id']]);
        $transaksiSiap = $stmtSiap->fetchAll(\PDO::FETCH_ASSOC);

        $pageTitle  = 'Detail TU — ' . $data['nomor_pengajuan'];
        $activePage = 'pengajuan_tu';
        $viewFile   = __DIR__ . '/../../views/pengajuan_tu/show.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ── SETUJUI ───────────────────────────────────────────────────────────

    public function setujui(int $id): void
    {
        $this->requireAdmin();
        $catatan = trim($_POST['catatan_admin'] ?? '');
        $ok = $this->model->setujui($id, $this->adminId(), $catatan ?: null);
        $this->logAudit($this->adminId(), 'tu_setujui', 'pengajuan_tu', $id, 'Setujui TU id ' . $id);
        $this->flash($ok ? 'success' : 'error', $ok ? 'Pengajuan TU disetujui.' : 'Gagal menyetujui — pastikan status masih "diajukan".');
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── TOLAK ─────────────────────────────────────────────────────────────

    public function tolak(int $id): void
    {
        $this->requireAdmin();
        $catatan = trim($_POST['catatan_admin'] ?? '');
        if ($catatan === '') {
            $this->flash('error', 'Catatan penolakan wajib diisi.');
            $this->redirect(base_url('pengajuan-tu/show/' . $id));
        }
        $ok = $this->model->tolak($id, $this->adminId(), $catatan);
        $this->logAudit($this->adminId(), 'tu_tolak', 'pengajuan_tu', $id, 'Tolak TU id ' . $id . ': ' . $catatan);
        $this->flash($ok ? 'success' : 'error', $ok ? 'Pengajuan TU ditolak.' : 'Gagal menolak.');
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── CAIRKAN ───────────────────────────────────────────────────────────

    public function cairkan(int $id): void
    {
        $this->requireAdmin();

        $nomorSp2d   = trim($_POST['nomor_sp2d']   ?? '');
        $tanggalCair = trim($_POST['tanggal_cair']  ?? '');
        $nominalRaw  = str_replace(['.', ','], ['', '.'], $_POST['nominal_cair'] ?? '0');
        $nominalCair = (float) $nominalRaw;
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

    // ── SELESAIKAN ────────────────────────────────────────────────────────

    public function selesaikan(int $id): void
    {
        $this->requireAdmin();
        $catatan = trim($_POST['catatan_admin'] ?? '');

        try {
            $result = $this->model->selesaikan($id, $this->adminId(), $catatan ?: null);
            $sisa   = $result['sisa'];
            $msg    = 'Siklus TU diselesaikan.';
            if ($sisa > 0) {
                $msg .= sprintf(' Sisa Rp %s disetor ke kas.', number_format($sisa, 0, ',', '.'));
            } else {
                $msg .= ' Tidak ada sisa (semua dana terpakai).';
            }
            $this->logAudit($this->adminId(), 'tu_selesai', 'pengajuan_tu', $id,
                'Selesaikan TU id ' . $id . ', sisa Rp ' . number_format($sisa, 0, ',', '.'));
            $this->flash('success', $msg);
        } catch (\Throwable $e) {
            error_log('selesaikan TU error: ' . $e->getMessage());
            $this->flash('error', 'Gagal menyelesaikan TU: ' . $e->getMessage());
        }
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── KAITKAN TRANSAKSI ─────────────────────────────────────────────────

    public function kaitkan(int $id): void
    {
        $this->requireAdmin();
        $db = \Database::getConnection();
        $transaksiIds = $_POST['transaksi_ids'] ?? [];
        if (!is_array($transaksiIds) || empty($transaksiIds)) {
            $this->flash('error', 'Pilih minimal satu transaksi.');
            $this->redirect(base_url('pengajuan-tu/show/' . $id));
        }

        $berhasil = 0;
        foreach ($transaksiIds as $tid) {
            if ($this->model->kaitkanTransaksi($id, (int) $tid, $db)) {
                $berhasil++;
            }
        }
        $this->logAudit($this->adminId(), 'tu_kaitkan', 'pengajuan_tu', $id,
            'Kaitkan ' . $berhasil . ' transaksi ke TU id ' . $id);
        $this->flash($berhasil > 0 ? 'success' : 'error',
            $berhasil > 0 ? "{$berhasil} transaksi berhasil dikaitkan ke TU ini." : 'Tidak ada transaksi yang berhasil dikaitkan.');
        $this->redirect(base_url('pengajuan-tu/show/' . $id));
    }

    // ── LEPAS TRANSAKSI ───────────────────────────────────────────────────

    public function lepas(int $tuId, int $transaksiId): void
    {
        $this->requireAdmin();
        $ok = $this->model->lepasTransaksi($tuId, $transaksiId);
        $this->logAudit($this->adminId(), 'tu_lepas', 'pengajuan_tu', $tuId,
            'Lepas transaksi id ' . $transaksiId . ' dari TU id ' . $tuId);
        $this->flash($ok ? 'success' : 'error',
            $ok ? 'Transaksi dilepas dari TU.' : 'Gagal melepas transaksi.');
        $this->redirect(base_url('pengajuan-tu/show/' . $tuId));
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
