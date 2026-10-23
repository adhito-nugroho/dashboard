<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\RincianBiaya;
use App\Repositories\SuratTugasRepository;
use App\Services\RincianBiayaInputService;

/**
 * Controller modul SPJ Rincian Biaya Perjalanan Dinas.
 *
 * Alur:
 *   /spj                          → index()   : daftar Surat Tugas (dari db_surat_tugas)
 *   /spj/detail/{st_id}           → detail()  : daftar pegawai + status rincian biaya
 *   /spj/create/{st_id}/{nip}     → create()  : form input baru
 *   /spj/store                    → store()   : simpan (POST)
 *   /spj/edit/{id}                → edit()    : form edit
 *   /spj/update/{id}              → update()  : simpan edit (POST)
 *   /spj/delete/{id}              → delete()  : hapus (POST)
 *
 * Hanya bisa diakses admin (is_admin = true di session).
 */
class SpjController
{
    private RincianBiaya $model;
    private SuratTugasRepository $suratTugas;
    private RincianBiayaInputService $input;

    public function __construct(
        RincianBiaya $model,
        ?SuratTugasRepository $suratTugas = null,
        ?RincianBiayaInputService $input = null
    ) {
        $this->model = $model;
        $this->suratTugas = $suratTugas ?? new SuratTugasRepository();
        $this->input = $input ?? new RincianBiayaInputService();
    }

    // ──────────────────────────────────────────────────────────
    // GUARD
    // ──────────────────────────────────────────────────────────

    /**
     * Izinkan semua user yang sudah login (seksi maupun admin).
     * Admin bisa mereview semua ST; seksi hanya bisa input rincian
     * untuk ST yang relevan (validasi lebih lanjut bisa ditambahkan di masing-masing action).
     */
    private function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    /** Shorthand — dipakai untuk action yang hanya boleh admin (review/hapus). */
    private function requireAdmin(): void
    {
        $this->requireLogin();
        if (empty($_SESSION['is_admin'])) {
            header('Location: ' . base_url('spj'));
            exit;
        }
    }

    // ──────────────────────────────────────────────────────────
    // INDEX — Daftar Surat Tugas (dari db_surat_tugas)
    // ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLogin();

        $suratTugasList = [];
        $stError = null;

        if (!$this->suratTugas->isAvailable()) {
            $stError = 'Koneksi ke database Surat Tugas tidak tersedia.';
        } else {
            $suratTugasList = $this->suratTugas->list([
                'q'     => trim($_GET['q'] ?? ''),
                'bulan' => $_GET['bulan'] ?? null,
                'tahun' => $_GET['tahun'] ?? null,
            ]);
        }

        $pageTitle  = 'SPJ Perjalanan Dinas';
        $activePage = 'spj';
        $viewFile   = __DIR__ . '/../../views/spj/index.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // DETAIL — Daftar pegawai dalam satu ST + status rincian biaya
    // ──────────────────────────────────────────────────────────

    public function detail(int $suratTugasId): void
    {
        $this->requireLogin();

        $suratTugas = null;
        $pegawaiList = [];
        $stError = null;

        if (!$this->suratTugas->isAvailable()) {
            $stError = 'Koneksi ke database Surat Tugas tidak tersedia.';
        } else {
            $suratTugas = $this->suratTugas->find($suratTugasId);
            if (!$suratTugas) {
                $this->redirectWithMessage(base_url('spj'), 'error', 'Surat Tugas tidak ditemukan.');
                return;
            }
            $pegawaiList = $this->suratTugas->pegawaiList($suratTugasId);
        }

        // Cek rincian biaya yang sudah ada untuk ST ini
        $rincianAda = [];
        if (!$stError && $suratTugas) {
            $existing = $this->model->getBySuratTugasId($suratTugasId);
            foreach ($existing as $r) {
                $rincianAda[trim($r['pegawai_nip'])] = $r;
            }
        }

        $pageTitle  = 'Detail SPJ — ' . ($suratTugas['nomor_surat'] ?? '');
        $activePage = 'spj';
        $viewFile   = __DIR__ . '/../../views/spj/detail.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // CREATE — Form input rincian biaya baru
    // ──────────────────────────────────────────────────────────

    public function create(int $suratTugasId, string $nip): void
    {
        $this->requireLogin();

        // Cek sudah ada
        $existingId = $this->model->findBySuratTugasDanNip($suratTugasId, $nip);
        if ($existingId) {
            $this->redirectWithMessage(
                base_url('spj/edit/' . $existingId),
                'info',
                'Rincian biaya untuk pegawai ini sudah ada. Silakan edit.'
            );
            return;
        }

        [$suratTugas, $pegawai, $stError] = $this->fetchStAndPegawai($suratTugasId, $nip);
        if ($stError || !$suratTugas || !$pegawai) {
            $this->redirectWithMessage(
                base_url('spj/detail/' . $suratTugasId),
                'error',
                $stError ?? 'Data pegawai tidak ditemukan.'
            );
            return;
        }

        $rincian  = null;   // mode create
        $details  = $this->input->defaultKomponen();
        $errors   = [];

        $pageTitle  = 'Isi Rincian Biaya — ' . ($pegawai['nama'] ?? $nip);
        $activePage = 'spj';
        $viewFile   = __DIR__ . '/../../views/spj/form.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // STORE — Simpan rincian biaya baru
    // ──────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireLogin();

        $suratTugasId = (int) ($_POST['surat_tugas_id'] ?? 0);
        $nip          = trim($_POST['pegawai_nip'] ?? '');
        $userId       = (int) ($_SESSION['user_id'] ?? 0);

        [$suratTugas, $pegawai, $stError] = $this->fetchStAndPegawai($suratTugasId, $nip);
        $parsed = $this->input->parse($_POST);
        $errors = $parsed['errors'];
        $details = $parsed['details'];
        $header = $parsed['header'];

        if (!$suratTugas && !$stError) {
            $errors[] = 'Surat Tugas tidak valid.';
        }
        if ($nip === '') {
            $errors[] = 'NIP pegawai tidak valid.';
        }
        // Cek duplikat
        if (empty($errors) && $this->model->findBySuratTugasDanNip($suratTugasId, $nip)) {
            $errors[] = 'Rincian biaya untuk pegawai ini sudah ada. Gunakan fitur Edit.';
        }

        if (!empty($errors)) {
            $rincian = null;
            $pageTitle  = 'Isi Rincian Biaya — ' . ($pegawai['nama'] ?? $nip);
            $activePage = 'spj';
            $viewFile   = __DIR__ . '/../../views/spj/form.php';
            include __DIR__ . '/../../views/layout.php';
            return;
        }

        try {
            $this->model->create(
                $suratTugasId,
                $suratTugas['nomor_surat'] ?? '',
                $nip,
                $pegawai['nama']     ?? $nip,
                $pegawai['pangkat']  ?? null,
                $pegawai['jabatan']  ?? null,
                $header['ditetapkan_sejumlah'],
                $header['dibayar_semula'],
                $header['tempat_tanggal'],
                $userId,
                $details
            );
            $this->redirectWithMessage(
                base_url('spj/detail/' . $suratTugasId),
                'success',
                'Rincian biaya berhasil disimpan.'
            );
        } catch (\Throwable $e) {
            error_log('SpjController::store error: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
            $rincian  = null;
            $pageTitle  = 'Isi Rincian Biaya — ' . ($pegawai['nama'] ?? $nip);
            $activePage = 'spj';
            $viewFile   = __DIR__ . '/../../views/spj/form.php';
            include __DIR__ . '/../../views/layout.php';
        }
    }

    // ──────────────────────────────────────────────────────────
    // EDIT — Form edit rincian biaya
    // ──────────────────────────────────────────────────────────

    public function edit(int $id): void
    {
        $this->requireLogin();

        $data = $this->model->getByIdWithDetails($id);
        if (!$data) {
            $this->redirectWithMessage(base_url('spj'), 'error', 'Data rincian biaya tidak ditemukan.');
            return;
        }

        $rincian = $data['header'];
        $details = $data['details'];
        $errors  = [];

        // Ambil data pegawai dari session cache (sudah disimpan di tabel)
        $pegawai = [
            'nip'     => $rincian['pegawai_nip'],
            'nama'    => $rincian['pegawai_nama'],
            'pangkat' => $rincian['pegawai_pangkat'],
            'jabatan' => $rincian['pegawai_jabatan'],
        ];

        // Ambil header ST dari db_surat_tugas untuk display
        $suratTugas = $this->fetchStOnly((int) $rincian['surat_tugas_id']);

        $pageTitle  = 'Edit Rincian Biaya — ' . $rincian['pegawai_nama'];
        $activePage = 'spj';
        $viewFile   = __DIR__ . '/../../views/spj/form.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // UPDATE — Simpan edit
    // ──────────────────────────────────────────────────────────

    public function update(int $id): void
    {
        $this->requireLogin();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $data = $this->model->getByIdWithDetails($id);
        if (!$data) {
            $this->redirectWithMessage(base_url('spj'), 'error', 'Data tidak ditemukan.');
            return;
        }

        $parsed = $this->input->parse($_POST);
        $errors = $parsed['errors'];
        $details = $parsed['details'];
        $header = $parsed['header'];

        if (!empty($errors)) {
            $rincian    = $data['header'];
            $details    = $details ?: $data['details'];
            $pegawai    = [
                'nip'     => $rincian['pegawai_nip'],
                'nama'    => $rincian['pegawai_nama'],
                'pangkat' => $rincian['pegawai_pangkat'],
                'jabatan' => $rincian['pegawai_jabatan'],
            ];
            $suratTugas = $this->fetchStOnly((int) $rincian['surat_tugas_id']);
            $pageTitle  = 'Edit Rincian Biaya — ' . $rincian['pegawai_nama'];
            $activePage = 'spj';
            $viewFile   = __DIR__ . '/../../views/spj/form.php';
            include __DIR__ . '/../../views/layout.php';
            return;
        }

        try {
            $this->model->update(
                $id,
                $header['ditetapkan_sejumlah'],
                $header['dibayar_semula'],
                $header['tempat_tanggal'],
                $userId,
                $details
            );
            $this->redirectWithMessage(
                base_url('spj/detail/' . $data['header']['surat_tugas_id']),
                'success',
                'Rincian biaya berhasil diperbarui.'
            );
        } catch (\Throwable $e) {
            error_log('SpjController::update error: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('spj'), 'error', 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────
    // DELETE
    // ──────────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        $this->requireAdmin();

        // Simpan ST ID sebelum hapus supaya bisa redirect kembali
        $data = $this->model->getByIdWithDetails($id);
        $suratTugasId = $data ? (int) $data['header']['surat_tugas_id'] : 0;

        $ok = $this->model->delete($id);
        $backUrl = $suratTugasId
            ? base_url('spj/detail/' . $suratTugasId)
            : base_url('spj');

        $this->redirectWithMessage(
            $backUrl,
            $ok ? 'success' : 'error',
            $ok ? 'Rincian biaya berhasil dihapus.' : 'Gagal menghapus rincian biaya.'
        );
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Ambil data ST + satu pegawai dari db_surat_tugas.
     * Return [$suratTugas|null, $pegawai|null, $errorMsg|null]
     */
    private function fetchStAndPegawai(int $stId, string $nip): array
    {
        if (!$this->suratTugas->isAvailable()) {
            return [null, null, 'Koneksi ke database Surat Tugas tidak tersedia.'];
        }

        $st  = $this->suratTugas->find($stId);
        $peg = $this->suratTugas->findPegawai($stId, $nip);

        return [$st, $peg, null];
    }

    /**
     * Ambil hanya header ST (untuk display di form edit).
     */
    private function fetchStOnly(int $stId): ?array
    {
        return $this->suratTugas->find($stId);
    }

    /**
     * Pratinjau / cetak PDF Rincian Biaya Perjalanan Dinas.
     * Akses: GET /spj/unduh/{id}
     */
    public function download(int $id): void
    {
        $this->requireLogin();
        $data = $this->model->getById($id);
        if (!$data) {
            http_response_code(404);
            echo 'Data rincian biaya tidak ditemukan.';
            exit;
        }

        $header  = $data['header'];
        $details = $data['details'];

        // Coba cari data transaksi terkait jika ada
        $transaksi = [];
        if (!empty($header['transaksi_id'])) {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM transaksi WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $header['transaksi_id']]);
            $transaksi = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        }

        $exportService = new \App\Services\RincianBiayaExportService();
        $exportService->streamPdf($header, $details, $transaksi);
    }

    /**
     * Unduh SPD (Surat Perjalanan Dinas, PDF) per pegawai — kelengkapan SPJ.
     * Akses: GET /spj/spd/{st_id}/{nip}
     * Aturan isi otomatis ada di SpdExportService (alat angkut, tingkat
     * biaya, akun dari transaksi, pengikut dikosongkan).
     */
    public function unduhSpd(int $suratTugasId, string $nip): void
    {
        $this->requireLogin();

        $svc = new \App\Services\SpdExportService();
        $data = $svc->buildData($suratTugasId, $nip);
        if ($data === null) {
            http_response_code(404);
            echo 'Data SPD tidak ditemukan (Surat Tugas/pegawai tidak valid atau database Surat Tugas tidak terjangkau).';
            exit;
        }

        if (!empty($data['kota_tujuan_kosong'])) {
            $this->renderKotaTujuanKosong(
                $svc->kotaTujuanKosongMessage((string) ($data['nomor_surat'] ?? '')),
                base_url('spj/detail/' . $suratTugasId)
            );
            return;
        }

        $svc->downloadPdf($data);
    }

    /**
     * Unduh SPD (PDF) berbasis ID transaksi db_anggaran (untuk tombol sejajar
     * Cetak Kuitansi di daftar transaksi). ST + NIP di-resolve otomatis
     * dari transaksi (ref_id → nomor surat → pegawai_nip).
     * Akses: GET /spj/spd-transaksi/{id}
     * Seksi hanya boleh mengunduh transaksi yang ia input sendiri.
     */
    public function unduhSpdTransaksi(int $transaksiId): void
    {
        $this->requireLogin();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $isAdmin = !empty($_SESSION['is_admin']);

        $db = \Database::getConnection();
        if ($isAdmin) {
            $stmtT = $db->prepare('SELECT * FROM transaksi WHERE id = ? LIMIT 1');
            $stmtT->execute([$transaksiId]);
        } else {
            $stmtT = $db->prepare('SELECT * FROM transaksi WHERE id = ? AND input_by = ? LIMIT 1');
            $stmtT->execute([$transaksiId, $userId]);
        }
        $trx = $stmtT->fetch(\PDO::FETCH_ASSOC);
        if (!$trx) {
            http_response_code(404);
            echo 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.';
            exit;
        }
        if (($trx['jenis_transaksi'] ?? '') !== 'perjalanan_dinas') {
            http_response_code(422);
            echo 'SPD hanya tersedia untuk transaksi Perjalanan Dinas.';
            exit;
        }

        $svc = new \App\Services\SpdExportService();
        $data = $svc->buildDataFromTransaksi($trx);
        if ($data === null) {
            http_response_code(404);
            echo 'SPD tidak dapat dibuat: transaksi ini belum tertaut ke Surat Tugas + NIP pegawai yang valid di database Surat Tugas.';
            exit;
        }

        if (!empty($data['kota_tujuan_kosong'])) {
            $backUrl = $isAdmin
                ? base_url('transaksi/show/' . $transaksiId)
                : base_url('seksi/transaksi');
            $this->renderKotaTujuanKosong(
                $svc->kotaTujuanKosongMessage((string) ($data['nomor_surat'] ?? $trx['nomor_surat_tugas'] ?? '')),
                $backUrl
            );
            return;
        }

        $svc->downloadPdf($data);
    }

    /**
     * Tampilkan notifikasi (halaman HTML) bahwa Kota Tujuan belum diisi di
     * aplikasi Surat Tugas, sehingga SPD PDF tidak dibuat dengan tujuan
     * yang keliru.
     */
    private function renderKotaTujuanKosong(string $message, string $backUrl): void
    {
        http_response_code(422);

        $noticeTitle     = 'Kota Tujuan Belum Diisi';
        $noticeMessage   = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $noticeVariant   = 'warning';
        $noticeHint      = '<i class="bi bi-info-circle me-1"></i>'
            . 'Field <strong>Kota Tujuan</strong> diisi pada aplikasi Surat Tugas. '
            . 'Setelah dilengkapi, buka kembali pratinjau SPD PDF ini.';
        $noticeBackUrl   = $backUrl;
        $noticeBackLabel = 'Kembali';

        include __DIR__ . '/../../views/shared/standalone_notice.php';
        exit;
    }

    private function redirectWithMessage(string $url, string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
        header('Location: ' . $url);
        exit;
    }
}

