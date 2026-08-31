<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/database_surat_tugas.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\RincianBiaya;

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

    public function __construct(RincianBiaya $model)
    {
        $this->model = $model;
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

        $pdo = \DatabaseSuratTugas::getConnection();
        $suratTugasList = [];
        $stError = null;

        if (!$pdo) {
            $stError = 'Koneksi ke database Surat Tugas tidak tersedia.';
        } else {
            try {
                $q     = trim($_GET['q']     ?? '');
                $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
                $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');

                $conditions = [];
                $params     = [];

                if ($q !== '') {
                    $conditions[] = '(nomor_surat LIKE :kw OR untuk LIKE :kw)';
                    $params[':kw'] = '%' . $q . '%';
                }
                if ($bulan !== null) {
                    $conditions[] = 'MONTH(tanggal_mulai) = :bulan';
                    $params[':bulan'] = $bulan;
                }
                $conditions[] = 'YEAR(tanggal_mulai) = :tahun';
                $params[':tahun'] = $tahun;

                $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

                $stmt = $pdo->prepare("
                    SELECT id, nomor_surat, tanggal_surat, untuk,
                           tanggal_mulai, tanggal_selesai, dasar_surat
                    FROM surat_tugas
                    {$where}
                    ORDER BY tanggal_mulai DESC, id DESC
                    LIMIT 100
                ");
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                }
                $stmt->execute();
                $suratTugasList = $stmt->fetchAll();
            } catch (\Throwable $e) {
                error_log('SpjController::index ST query error: ' . $e->getMessage());
                $stError = 'Gagal memuat daftar Surat Tugas: ' . $e->getMessage();
            }
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

        $pdo = \DatabaseSuratTugas::getConnection();
        $suratTugas = null;
        $pegawaiList = [];
        $stError = null;

        if (!$pdo) {
            $stError = 'Koneksi ke database Surat Tugas tidak tersedia.';
        } else {
            try {
                // Ambil header ST
                $stStmt = $pdo->prepare("
                    SELECT id, nomor_surat, tanggal_surat, untuk,
                           tanggal_mulai, tanggal_selesai, dasar_surat
                    FROM surat_tugas WHERE id = ? LIMIT 1
                ");
                $stStmt->execute([$suratTugasId]);
                $suratTugas = $stStmt->fetch();

                if (!$suratTugas) {
                    $this->redirectWithMessage(base_url('spj'), 'error', 'Surat Tugas tidak ditemukan.');
                    return;
                }

                // Ambil daftar pegawai dalam ST
                $pegStmt = $pdo->prepare("
                    SELECT
                        pt.nip AS pt_nip,
                        COALESCE(p.nip, pt.nip)           AS nip,
                        COALESCE(p.nama, pt.nip, 'Pegawai') AS nama,
                        p.pangkat,
                        p.jabatan,
                        pt.urutan
                    FROM pegawai_tugas pt
                    LEFT JOIN pegawai p ON (
                        p.nip = pt.nip
                        OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                    )
                    WHERE pt.id_surat_tugas = ?
                    ORDER BY pt.urutan ASC, p.nama ASC
                ");
                $pegStmt->execute([$suratTugasId]);
                $pegawaiList = $pegStmt->fetchAll();
            } catch (\Throwable $e) {
                error_log('SpjController::detail query error: ' . $e->getMessage());
                $stError = 'Gagal memuat data pegawai: ' . $e->getMessage();
            }
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
        $details  = $this->defaultKomponen();
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
        [$errors, $details, $header] = $this->validatePost($_POST);

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

        [$errors, $details, $header] = $this->validatePost($_POST);

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
        $pdo = \DatabaseSuratTugas::getConnection();
        if (!$pdo) {
            return [null, null, 'Koneksi ke database Surat Tugas tidak tersedia.'];
        }
        try {
            $stStmt = $pdo->prepare("
                SELECT id, nomor_surat, tanggal_surat, untuk,
                       tanggal_mulai, tanggal_selesai, dasar_surat
                FROM surat_tugas WHERE id = ? LIMIT 1
            ");
            $stStmt->execute([$stId]);
            $st = $stStmt->fetch();

            $pegStmt = $pdo->prepare("
                SELECT
                    COALESCE(p.nip, pt.nip)             AS nip,
                    COALESCE(p.nama, pt.nip, 'Pegawai') AS nama,
                    p.pangkat,
                    p.jabatan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas = ?
                  AND (pt.nip = ? OR REPLACE(pt.nip, ' ', '') = REPLACE(?, ' ', ''))
                LIMIT 1
            ");
            $pegStmt->execute([$stId, $nip, $nip]);
            $peg = $pegStmt->fetch() ?: null;

            return [$st ?: null, $peg, null];
        } catch (\Throwable $e) {
            error_log('SpjController::fetchStAndPegawai error: ' . $e->getMessage());
            return [null, null, 'Gagal memuat data: ' . $e->getMessage()];
        }
    }

    /**
     * Ambil hanya header ST (untuk display di form edit).
     */
    private function fetchStOnly(int $stId): ?array
    {
        $pdo = \DatabaseSuratTugas::getConnection();
        if (!$pdo) return null;
        try {
            $stmt = $pdo->prepare("
                SELECT id, nomor_surat, tanggal_surat, untuk,
                       tanggal_mulai, tanggal_selesai
                FROM surat_tugas WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$stId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validasi POST dan kembalikan [$errors, $details, $header].
     */
    private function validatePost(array $post): array
    {
        $errors  = [];
        $details = [];

        $ditetapkan   = (float) str_replace(['.', ','], ['', '.'], $post['ditetapkan_sejumlah'] ?? '0');
        $dibayar      = (float) str_replace(['.', ','], ['', '.'], $post['dibayar_semula']      ?? '0');
        $tempatTgl    = trim($post['tempat_tanggal'] ?? '') ?: null;

        // Validasi detail komponen
        $komponenList = $post['komponen'] ?? [];
        if (empty($komponenList) || !is_array($komponenList)) {
            $errors[] = 'Minimal harus ada satu baris komponen biaya.';
        } else {
            $adaIsiValid = false;
            foreach ($komponenList as $i => $k) {
                $nama = trim($k['nama_komponen'] ?? '');
                if ($nama === '') continue; // skip baris kosong

                $harga     = (float) str_replace(['.', ','], ['', '.'], $k['harga_satuan'] ?? '0');
                $hari      = isset($k['jumlah_hari']) && $k['jumlah_hari'] !== '' ? (float) $k['jumlah_hari'] : null;
                $jumlahRaw = str_replace(['.', ','], ['', '.'], $k['jumlah'] ?? '0');
                $jumlah    = (float) $jumlahRaw;

                if ($harga <= 0) {
                    $errors[] = "Baris " . ($i + 1) . " ($nama): Harga satuan harus lebih dari 0.";
                    continue;
                }
                if ($jumlah <= 0) {
                    // Hitung ulang dari harga * hari
                    $jumlah = $hari !== null ? $harga * $hari : $harga;
                }

                $details[] = [
                    'nama_komponen' => $nama,
                    'harga_satuan'  => $harga,
                    'jumlah_hari'   => $hari,
                    'jumlah'        => $jumlah,
                    'keterangan'    => trim($k['keterangan'] ?? '') ?: null,
                ];
                $adaIsiValid = true;
            }
            if (!$adaIsiValid && empty($errors)) {
                $errors[] = 'Minimal harus ada satu baris komponen biaya yang terisi.';
            }
        }

        $header = [
            'ditetapkan_sejumlah' => $ditetapkan,
            'dibayar_semula'      => $dibayar,
            'tempat_tanggal'      => $tempatTgl,
        ];

        return [$errors, $details, $header];
    }

    /**
     * Komponen default untuk form baru.
     */
    private function defaultKomponen(): array
    {
        return [
            ['nama_komponen' => 'Uang Harian',  'harga_satuan' => 0, 'jumlah_hari' => 1, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'BBM',           'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Tol',           'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Hotel',         'harga_satuan' => 0, 'jumlah_hari' => 1, 'jumlah' => 0, 'keterangan' => ''],
        ];
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
