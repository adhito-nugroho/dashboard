<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

use App\Models\Transaksi;

/**
 * Input transaksi mandiri oleh role seksi (RLPM, TKUK) + halaman "Transaksi Saya".
 */
class SeksiTransaksiController
{
    private Transaksi $transaksiModel;

    public function __construct(?Transaksi $transaksiModel = null)
    {
        require_once __DIR__ . '/../Models/Transaksi.php';
        if ($transaksiModel) {
            $this->transaksiModel = $transaksiModel;
        } else {
            $this->transaksiModel = new Transaksi(\Database::getConnection());
        }
    }

    private function requireSeksi(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['rlpm', 'tkuk', 'tu', 'seksi'], true)) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function userSeksiId(): int
    {
        return (int) ($_SESSION['seksi_id'] ?? 0);
    }

    /**
     * Halaman "Transaksi Saya" — daftar transaksi yang diinput user seksi ini.
     */
    public function index(): void
    {
        $this->requireSeksi();
        $db = \Database::getConnection();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $transaksis = $this->transaksiModel->getBySeksi(0, $userId);

        $pageTitle = 'Transaksi Saya';
        $activePage = 'transaksi';
        $viewFile = __DIR__ . '/../../views/seksi/transaksi_index.php';
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    /**
     * Form input transaksi baru oleh seksi.
     */
    public function create(): void
    {
        $this->requireSeksi();
        $db = \Database::getConnection();
        $seksiId = $this->userSeksiId();

        // Program yang memiliki sub_kegiatan milik seksi ini
        $programs = $db->prepare("
            SELECT DISTINCT p.id, p.kode_program, p.nama_program
            FROM program p
            INNER JOIN kegiatan k ON k.program_id = p.id
            INNER JOIN sub_kegiatan sk ON sk.kegiatan_id = k.id
            WHERE sk.seksi_id = ?
            ORDER BY p.kode_program ASC
        ");
        $programs->execute([$seksiId]);

        $pageTitle = 'Tambah Transaksi';
        $activePage = 'transaksi';
        $viewFile = __DIR__ . '/../../views/seksi/transaksi_form.php';
        $transaksi = null;
        $action = 'store';
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    /**
     * Simpan transaksi baru oleh seksi (status 'diajukan').
     */
    public function store(): void
    {
        $this->requireSeksi();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $seksiId = $this->userSeksiId();

        $db = \Database::getConnection();

        $errors = $this->validate($_POST, $db, $seksiId);

        if (!empty($errors)) {
            $programs = $db->prepare("
                SELECT DISTINCT p.id, p.kode_program, p.nama_program
                FROM program p
                INNER JOIN kegiatan k ON k.program_id = p.id
                INNER JOIN sub_kegiatan sk ON sk.kegiatan_id = k.id
                WHERE sk.seksi_id = ?
                ORDER BY p.kode_program ASC
            ");
            $programs->execute([$seksiId]);
            $pageTitle = 'Tambah Transaksi';
            $activePage = 'transaksi';
            $viewFile = __DIR__ . '/../../views/seksi/transaksi_form.php';
            $transaksi = $_POST;
            $action = 'store';
            $validationErrors = $errors;
            include __DIR__ . '/../../views/layout_seksi.php';
            return;
        }

        // Pastikan rekening milik seksi user
        if (!$this->rekeningMilikSeksi((int) $_POST['rekening_id'], $seksiId, $db)) {
            $this->redirectWithMessage(base_url('seksi/transaksi'), 'error', 'Rekening tidak valid untuk seksi Anda');
            return;
        }

        $id = $this->transaksiModel->createSeksi(
            $_POST['tanggal'],
            $seksiId,
            (int) $_POST['rekening_id'],
            trim($_POST['uraian']),
            (float) str_replace(['.', ','], '', $_POST['nilai']),
            trim($_POST['nomor_bukti']),
            $userId
        );

        $this->logAudit($userId, 'input_transaksi_seksi', 'transaksi', $id, 'Input transaksi oleh seksi menunggu verifikasi');
        $this->redirectWithMessage(base_url('seksi/transaksi'), 'success', 'Transaksi diajukan. Menunggu verifikasi admin.');
    }

    /**
     * Form edit transaksi oleh seksi (hanya status 'diajukan').
     */
    public function edit(int $id): void
    {
        $this->requireSeksi();
        $db = \Database::getConnection();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $seksiId = $this->userSeksiId();

        $transaksi = $this->transaksiModel->getById($id);
        if (!$transaksi || (int) $transaksi['input_by'] !== $userId || ($transaksi['status'] ?? 'diverifikasi') !== 'diajukan') {
            $this->redirectWithMessage(base_url('seksi/transaksi'), 'error', 'Transaksi tidak dapat diedit');
            return;
        }

        $rekening = $db->prepare("SELECT * FROM rekening WHERE id = ?");
        $rekening->execute([$transaksi['rekening_id']]);
        $rekeningData = $rekening->fetch();

        $programs = $db->prepare("
            SELECT DISTINCT p.id, p.kode_program, p.nama_program
            FROM program p
            INNER JOIN kegiatan k ON k.program_id = p.id
            INNER JOIN sub_kegiatan sk ON sk.kegiatan_id = k.id
            WHERE sk.seksi_id = ?
            ORDER BY p.kode_program ASC
        ");
        $programs->execute([$seksiId]);

        $transaksi['program_id'] = $rekeningData['program_id'] ?? '';
        $transaksi['kegiatan_id'] = $rekeningData['kegiatan_id'] ?? '';
        $transaksi['sub_kegiatan_id'] = $rekeningData['sub_kegiatan_id'] ?? '';

        $pageTitle = 'Edit Transaksi';
        $activePage = 'transaksi';
        $viewFile = __DIR__ . '/../../views/seksi/transaksi_form.php';
        $action = 'update/' . $id;
        include __DIR__ . '/../../views/layout_seksi.php';
    }

    /**
     * Update transaksi oleh seksi (hanya status 'diajukan').
     */
    public function update(int $id): void
    {
        $this->requireSeksi();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $seksiId = $this->userSeksiId();
        $db = \Database::getConnection();

        $existing = $this->transaksiModel->getById($id);
        if (!$existing || (int) $existing['input_by'] !== $userId || ($existing['status'] ?? 'diverifikasi') !== 'diajukan') {
            $this->redirectWithMessage(base_url('seksi/transaksi'), 'error', 'Transaksi tidak dapat diedit');
            return;
        }

        $errors = $this->validate($_POST, $db, $seksiId);
        if (!empty($errors)) {
            $_SESSION['flash_message'] = implode(' | ', $errors);
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('seksi/transaksi/edit/' . $id));
            exit;
        }

        if (!$this->rekeningMilikSeksi((int) $_POST['rekening_id'], $seksiId, $db)) {
            $this->redirectWithMessage(base_url('seksi/transaksi'), 'error', 'Rekening tidak valid untuk seksi Anda');
            return;
        }

        $ok = $this->transaksiModel->updateSeksi(
            $id,
            $userId,
            $_POST['tanggal'],
            $seksiId,
            (int) $_POST['rekening_id'],
            trim($_POST['uraian']),
            (float) str_replace(['.', ','], '', $_POST['nilai']),
            trim($_POST['nomor_bukti'])
        );

        $this->logAudit($userId, 'update_transaksi_seksi', 'transaksi', $id, $ok ? 'Update transaksi seksi' : 'Gagal update transaksi seksi');
        $this->redirectWithMessage(base_url('seksi/transaksi'), $ok ? 'success' : 'error', $ok ? 'Transaksi berhasil diperbarui' : 'Transaksi tidak dapat diperbarui');
    }

    /**
     * Delete transaksi oleh seksi (hanya status 'diajukan').
     */
    public function delete(int $id): void
    {
        $this->requireSeksi();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $ok = $this->transaksiModel->deleteSeksi($id, $userId);
        $this->logAudit($userId, 'delete_transaksi_seksi', 'transaksi', $id, $ok ? 'Hapus transaksi seksi' : 'Gagal hapus transaksi seksi');
        $this->redirectWithMessage(base_url('seksi/transaksi'), $ok ? 'success' : 'error', $ok ? 'Transaksi berhasil dihapus' : 'Transaksi tidak dapat dihapus');
    }

    /**
     * AJAX: Kegiatan milik seksi user (berdasarkan program_id).
     */
    public function getKegiatans(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        $db = \Database::getConnection();
        $seksiId = $this->userSeksiId();
        $programId = (int) ($_GET['program_id'] ?? 0);

        if (!$programId) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT DISTINCT k.id, k.kode_kegiatan, k.nama_kegiatan
            FROM kegiatan k
            INNER JOIN sub_kegiatan sk ON sk.kegiatan_id = k.id
            WHERE k.program_id = ? AND sk.seksi_id = ?
            ORDER BY k.kode_kegiatan ASC
        ");
        $stmt->execute([$programId, $seksiId]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    /**
     * AJAX: Sub kegiatan milik seksi user (berdasarkan kegiatan_id).
     */
    public function getSubKegiatans(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        $db = \Database::getConnection();
        $seksiId = $this->userSeksiId();
        $kegiatanId = (int) ($_GET['kegiatan_id'] ?? 0);

        if (!$kegiatanId) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, kode_sub_kegiatan, nama_sub_kegiatan
            FROM sub_kegiatan
            WHERE kegiatan_id = ? AND seksi_id = ?
            ORDER BY kode_sub_kegiatan ASC
        ");
        $stmt->execute([$kegiatanId, $seksiId]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    /**
     * AJAX: Rekening milik seksi user (berdasarkan sub_kegiatan_id).
     */
    public function getRekenings(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        $db = \Database::getConnection();
        $seksiId = $this->userSeksiId();
        $subKegiatanId = (int) ($_GET['sub_kegiatan_id'] ?? 0);

        if (!$subKegiatanId) {
            echo json_encode([]);
            exit;
        }

        // Cek sub_kegiatan milik seksi
        $check = $db->prepare("SELECT COUNT(*) FROM sub_kegiatan WHERE id = ? AND seksi_id = ?");
        $check->execute([$subKegiatanId, $seksiId]);
        if ((int) $check->fetchColumn() === 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, kode_rekening, nama_rekening
            FROM rekening
            WHERE sub_kegiatan_id = ?
            ORDER BY kode_rekening ASC
        ");
        $stmt->execute([$subKegiatanId]);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    private function validate(array $data, \PDO $db, int $seksiId): array
    {
        $errors = [];

        if (empty($data['tanggal'])) {
            $errors[] = 'Tanggal wajib diisi';
        } elseif (!strtotime($data['tanggal'])) {
            $errors[] = 'Format tanggal tidak valid';
        }

        if (empty($data['rekening_id'])) {
            $errors[] = 'Rekening wajib dipilih';
        }

        if (empty(trim($data['uraian'] ?? ''))) {
            $errors[] = 'Uraian wajib diisi';
        }

        if (empty($data['nilai'])) {
            $errors[] = 'Nilai wajib diisi';
        } else {
            $nilai = str_replace(['.', ','], '', $data['nilai']);
            if (!is_numeric($nilai) || (float) $nilai <= 0) {
                $errors[] = 'Nilai harus berupa angka lebih dari 0';
            }
        }

        if (empty(trim($data['nomor_bukti'] ?? ''))) {
            $errors[] = 'Nomor bukti wajib diisi';
        }

        return $errors;
    }

    private function rekeningMilikSeksi(int $rekeningId, int $seksiId, \PDO $db): bool
    {
        if (!$rekeningId) {
            return false;
        }
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM rekening r
            INNER JOIN sub_kegiatan sk ON sk.id = r.sub_kegiatan_id
            WHERE r.id = ? AND sk.seksi_id = ?
        ");
        $stmt->execute([$rekeningId, $seksiId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function logAudit(int $userId, string $aksi, string $tabel, ?int $recordId, string $keterangan): void
    {
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare('INSERT INTO audit_log (user_id, aksi, tabel, record_id, keterangan) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $aksi, $tabel, $recordId, $keterangan]);
        } catch (\Exception $e) {
            error_log('audit_log error: ' . $e->getMessage());
        }
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
