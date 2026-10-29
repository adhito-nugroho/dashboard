<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\KalibrasiSppdLembar2Elemen;
use App\Models\PrinterKuitansi;
use App\Models\RincianBiaya;
use App\Models\SppdLembar2Pejabat;
use App\Repositories\SuratTugasRepository;
use App\Services\RincianBiayaInputService;
use App\Services\SppdLembar2Service;

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
     * Form isian sebelum mencetak lembar 2 SPPD (overlay).
     * Akses: GET /spj/sppd2/{st_id}/{nip}
     * Isi otomatis: Kota Tujuan, tanggal tiba (tgl mulai), tanggal berangkat
     * (tgl selesai), tujuan kembali (Bojonegoro). Diinput saat cetak:
     * jabatan + nama + NIP pejabat instansi tujuan (disimpan per Surat Tugas).
     */
    public function sppdLembar2(int $suratTugasId, string $nip): void
    {
        $this->requireLogin();

        $svc = new SppdLembar2Service();
        $values = $svc->buildValues($suratTugasId, $nip);
        if ($values === null) {
            $this->redirectWithMessage(
                base_url('spj/detail/' . $suratTugasId),
                'error',
                'SPPD lembar 2 tidak dapat dibuat: Surat Tugas/pegawai tidak ditemukan di database Surat Tugas.'
            );
            return;
        }
        if (!empty($values['kota_tujuan_kosong'])) {
            $this->renderKotaTujuanKosong(
                'Surat Tugas ' . ($values['nomor_surat'] !== '' ? $values['nomor_surat'] . ' ' : '')
                    . 'belum mengisi field "Kota Tujuan" di aplikasi Surat Tugas. '
                    . 'Kolom "Tiba di" dan "Berangkat dari" pada lembar 2 SPPD membutuhkan Kota Tujuan.',
                base_url('spj/detail/' . $suratTugasId)
            );
            return;
        }

        $suratTugas = $this->fetchStOnly($suratTugasId);
        $pegawai = $this->fetchStAndPegawai($suratTugasId, $nip)[1];
        $pejabat = (new SppdLembar2Pejabat(\Database::getConnection()))
            ->getBySuratTugasId($suratTugasId)
            ?? ['jabatan' => '', 'nama' => '', 'nip' => ''];

        $pageTitle = 'Cetak Lembar 2 SPPD — ' . ($values['pegawai_nama'] ?? $nip);
        $activePage = 'spj';
        $viewFile = __DIR__ . '/../../views/spj/sppd_lembar2_form.php';
        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Simpan pejabat tujuan lalu cetak PDF lembar 2 SPPD (overlay A4).
     * Akses: POST /spj/sppd2/cetak/{st_id}/{nip}
     */
    public function cetakSppdLembar2(int $suratTugasId, string $nip): void
    {
        $this->requireLogin();

        $svc = new SppdLembar2Service();
        $values = $svc->buildValues($suratTugasId, $nip);
        if ($values === null) {
            http_response_code(404);
            echo 'SPPD lembar 2 tidak dapat dibuat: Surat Tugas/pegawai tidak ditemukan.';
            exit;
        }
        if (!empty($values['kota_tujuan_kosong'])) {
            $this->renderKotaTujuanKosong(
                'Surat Tugas ini belum mengisi field "Kota Tujuan" di aplikasi Surat Tugas. '
                    . 'Kolom "Tiba di" dan "Berangkat dari" pada lembar 2 SPPD membutuhkan Kota Tujuan.',
                base_url('spj/detail/' . $suratTugasId)
            );
            return;
        }

        $jabatan = trim((string) ($_POST['jabatan'] ?? ''));
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $nipPejabat = (string) preg_replace('/[^0-9]/', '', (string) ($_POST['nip'] ?? ''));

        if ($jabatan === '' || $nama === '' || $nipPejabat === '') {
            $this->redirectWithMessage(
                base_url('spj/sppd2/' . $suratTugasId . '/' . urlencode($nip)),
                'error',
                'Jabatan, nama, dan NIP pejabat instansi tujuan wajib diisi.'
            );
            return;
        }

        try {
            (new SppdLembar2Pejabat(\Database::getConnection()))->save(
                $suratTugasId,
                $jabatan,
                $nama,
                $nipPejabat,
                $this->actor()
            );
        } catch (\Throwable $e) {
            error_log('SpjController::cetakSppdLembar2 save error: ' . $e->getMessage());
            $this->redirectWithMessage(
                base_url('spj/sppd2/' . $suratTugasId . '/' . urlencode($nip)),
                'error',
                'Gagal menyimpan data pejabat tujuan: ' . $e->getMessage()
            );
            return;
        }

        $this->applySppdCalibration($svc);
        $svc->streamPdf($values, ['jabatan' => $jabatan, 'nama' => $nama, 'nip' => $nipPejabat]);
        exit;
    }

    /** Halaman uji posisi (kalibrasi) lembar 2 SPPD. Akses: GET /spj/sppd2/uji */
    public function cetakUjiSppdLembar2(): void
    {
        $this->requireKalibrasiAccess();
        $svc = new SppdLembar2Service();
        $this->applySppdCalibration($svc);
        $svc->streamTestPage();
        exit;
    }

    // ──────────────────────────────────────────────────────────
    // KALIBRASI POSISI LEMBAR 2 SPPD (per printer)
    // ──────────────────────────────────────────────────────────

    public const REF_DIR = __DIR__ . '/../../public/uploads/kalibrasi';
    public const REF_BASE = 'referensi_sppd_lembar2';

    private function requireKalibrasiAccess(): void
    {
        $this->requireLogin();
        $isAdmin = !empty($_SESSION['is_admin']);
        $isSeksi = in_array($_SESSION['role'] ?? '', ['rlpm', 'tkuk', 'tu', 'seksi'], true);
        if (!($isAdmin || $isSeksi)) {
            http_response_code(403);
            echo 'Akses ditolak: halaman kalibrasi hanya untuk admin/seksi.';
            exit;
        }
    }

    /** Posisi tersimpan printer default diterapkan ke service cetak. */
    private function applySppdCalibration(SppdLembar2Service $svc): void
    {
        try {
            $db = \Database::getConnection();
            $printerId = (new PrinterKuitansi($db))->getDefaultId();
            $svc->setPositions((new KalibrasiSppdLembar2Elemen($db))->getAll($printerId));
        } catch (\Throwable $e) {
            error_log('SpjController::applySppdCalibration: ' . $e->getMessage());
        }
    }

    /** File contoh blanko lembar 2 aktif (gambar/PDF, terbaru menang). */
    public static function referenceInfoSppdLembar2(): ?array
    {
        $best = null;
        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            $file = self::REF_DIR . '/' . self::REF_BASE . '.' . $ext;
            if (is_file($file)) {
                $mt = filemtime($file);
                if ($best === null || $mt > $best['mtime']) {
                    $best = ['url' => base_url('uploads/kalibrasi/' . self::REF_BASE . '.' . $ext) . '?v=' . $mt, 'kind' => 'image', 'mtime' => $mt];
                }
            }
        }
        $pdf = self::REF_DIR . '/' . self::REF_BASE . '.pdf';
        if (is_file($pdf)) {
            $mt = filemtime($pdf);
            if ($best === null || $mt > $best['mtime']) {
                $best = ['url' => base_url('uploads/kalibrasi/' . self::REF_BASE . '.pdf') . '?v=' . $mt, 'kind' => 'pdf', 'mtime' => $mt];
            }
        }
        if ($best === null) {
            return null;
        }
        unset($best['mtime']);
        return $best;
    }

    /**
     * Halaman kanvas kalibrasi posisi lembar 2 SPPD.
     * Akses: GET /spj/sppd2/kalibrasi[?printer=ID]
     */
    public function kalibrasiSppdLembar2(): void
    {
        $this->requireKalibrasiAccess();

        $db = \Database::getConnection();
        $printerModel = new PrinterKuitansi($db);
        $elemenModel = new KalibrasiSppdLembar2Elemen($db);

        $printers = $printerModel->getAll();
        $reqPid = isset($_GET['printer']) ? (int) $_GET['printer'] : 0;
        $printerId = $reqPid > 0 && $printerModel->get($reqPid) !== null ? $reqPid : $printerModel->getDefaultId();
        $activePrinter = null;
        foreach ($printers as $p) {
            if ((int) ($p['id'] ?? 0) === $printerId) {
                $activePrinter = $p;
                break;
            }
        }

        $svc = new SppdLembar2Service();
        $svc->setPositions($elemenModel->getAll($printerId));
        $positions = $svc->getPositions();
        $defaults = $svc->getDefaults();
        $widths = [];
        $defaultWidths = [];
        $defaultLines = [];
        $labels = [];
        $order = [];
        $cfg = $svc->getConfig();
        foreach (array_keys($cfg['elements'] ?? []) as $key) {
            $order[] = $key;
            $widths[$key] = $svc->widthOf($key);
            $defaultWidths[$key] = (float) ($defaults[$key]['w_mm'] ?? 50);
            $defaultLines[$key] = (int) ($defaults[$key]['max_lines'] ?? 1);
            $labels[$key] = (string) ($cfg['elements'][$key]['label'] ?? $key);
        }

        $flash = $_SESSION['flash_message'] ?? null;
        $flashType = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $ref = self::referenceInfoSppdLembar2();
        $kalibrasiData = [
            'positions' => $positions,
            'defaults' => $defaults,
            'widths' => $widths,
            'defaultWidths' => $defaultWidths,
            'defaultLines' => $defaultLines,
            'labels' => $labels,
            'order' => $order,
            'sampleTexts' => $svc->sampleTexts(),
            'refUrl' => $ref['url'] ?? null,
            'refKind' => $ref['kind'] ?? null,
            'flash' => $flash,
            'flashType' => $flashType,
            'printers' => $printers,
            'printerId' => $printerId,
            'content' => $cfg['content'] ?? [],
            'offset' => $cfg['offset_mm'] ?? ['x' => 0, 'y' => 0],
        ];

        $pageTitle = 'Kalibrasi Cetak Lembar 2 SPPD';
        $activePage = 'kalibrasi_sppd2';
        $viewFile = __DIR__ . '/../../views/spj/sppd2_kalibrasi.php';
        $layout = !empty($_SESSION['is_admin'])
            ? __DIR__ . '/../../views/layout.php'
            : __DIR__ . '/../../views/layout_seksi.php';
        include $layout;
    }

    /**
     * Simpan posisi semua elemen satu printer.
     * Body JSON: {"printer_id":1,"items":[{"elemen_key","label","x_mm","y_mm","max_width_mm"|null,"max_lines"|null}, ...]}
     */
    public function simpanKalibrasiSppd(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];
        $printerId = (int) ($body['printer_id'] ?? 0);
        if ($printerId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Pilih printer dulu. Tambahkan printer di halaman Kalibrasi Kuitansi.']);
            return;
        }
        try {
            $db = \Database::getConnection();
            if ((new PrinterKuitansi($db))->get($printerId) === null) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Printer tidak dikenal.']);
                return;
            }
            $allowed = array_keys((new SppdLembar2Service())->getConfig()['elements'] ?? []);
            $items = array_values(array_filter(
                is_array($body['items'] ?? null) ? $body['items'] : [],
                fn($it) => in_array((string) ($it['elemen_key'] ?? ''), $allowed, true)
            ));
            if ($items === []) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Tidak ada elemen_key yang dikenal.']);
                return;
            }
            $count = (new KalibrasiSppdLembar2Elemen($db))->saveAll($printerId, $items, $this->actor());
            echo json_encode(['ok' => true, 'saved' => $count, 'printer_id' => $printerId]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
        }
    }

    /**
     * Cetak uji dari posisi kanvas (belum tentu tersimpan).
     * Body JSON: {"positions":{key:{"x_mm","y_mm","max_width_mm"|null,"max_lines"|null}}}
     */
    public function ujiKalibrasiSppd(): void
    {
        $this->requireKalibrasiAccess();
        $body = json_decode((string) file_get_contents('php://input'), true);
        $positions = is_array($body['positions'] ?? null) ? $body['positions'] : [];
        (new SppdLembar2Service())->streamTestPage($positions);
        exit;
    }

    /**
     * Upload contoh blanko lembar 2 (JPG/PNG maks 5MB atau PDF-scan maks 10MB)
     * sebagai background kanvas kalibrasi.
     */
    public function uploadReferensiSppd(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $f = $_FILES['referensi'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Pilih file JPG/PNG/PDF terlebih dahulu.']);
            return;
        }

        $tmp = (string) $f['tmp_name'];
        $isPdf = str_starts_with((string) @file_get_contents($tmp, false, null, 0, 5), '%PDF');
        $mime = @getimagesize($tmp)['mime'] ?? '';
        if ($mime === '') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($tmp);
        }

        if ($isPdf || $mime === 'application/pdf') {
            if ((int) $f['size'] > 10 * 1024 * 1024) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'PDF maksimal 10MB.']);
                return;
            }
            if (!is_dir(self::REF_DIR)) {
                mkdir(self::REF_DIR, 0755, true);
            }
            @unlink(self::REF_DIR . '/' . self::REF_BASE . '.pdf');
            if (!move_uploaded_file($tmp, self::REF_DIR . '/' . self::REF_BASE . '.pdf')) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan file di server.']);
                return;
            }
            $info = self::referenceInfoSppdLembar2();
            echo json_encode(['ok' => true, 'url' => $info['url'] ?? null, 'kind' => 'pdf']);
            return;
        }

        if ((int) $f['size'] > 5 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Ukuran maksimal 5MB.']);
            return;
        }
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Hanya JPG/PNG/PDF.']);
            return;
        }

        if (!is_dir(self::REF_DIR)) {
            mkdir(self::REF_DIR, 0755, true);
        }
        foreach (glob(self::REF_DIR . '/' . self::REF_BASE . '.{jpg,jpeg,png}', GLOB_BRACE) ?: [] as $old) {
            @unlink($old);
        }
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        if (!move_uploaded_file($tmp, self::REF_DIR . '/' . self::REF_BASE . '.' . $ext)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan file di server.']);
            return;
        }
        $info = self::referenceInfoSppdLembar2();
        echo json_encode(['ok' => true, 'url' => $info['url'] ?? null, 'kind' => 'image']);
    }

    private function actor(): string
    {
        return (string) ($_SESSION['username'] ?? ('user#' . ($_SESSION['user_id'] ?? '?')));
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

