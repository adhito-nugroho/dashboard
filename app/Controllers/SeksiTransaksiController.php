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
     * Mendukung filter status/bulan/tahun/q + pagination (10/halaman).
     */
    public function index(): void
    {
        $this->requireSeksi();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $bulan  = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int)$_GET['bulan'] : null;
        $tahun  = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int)$_GET['tahun'] : null;
        $q      = isset($_GET['q']) ? trim($_GET['q']) : '';

        $allowedStatus = ['diajukan','diverifikasi','ditolak'];
        if ($status !== '' && !in_array($status, $allowedStatus, true)) $status = '';

        $perPage = 10;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $total = $this->transaksiModel->countBySeksiFiltered($userId, $status ?: null, $bulan, $tahun, $q ?: null);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $transaksis = $this->transaksiModel->getBySeksiFiltered($userId, $status ?: null, $bulan, $tahun, $q ?: null, $perPage, $offset);

        $hasFilter = ($status !== '' || $bulan !== null || $tahun !== null || $q !== '');
        $filters = ['status'=>$status,'bulan'=>$bulan,'tahun'=>$tahun,'q'=>$q];

        // Opsi A: hitung counter per pill hormati bulan/tahun/q (ignore status) — 1 query GROUP BY, bukan N+1
        $statusCounts = $this->transaksiModel->getStatusCountsBySeksi($userId, $bulan, $tahun, $q ?: null);

        // tahun list untuk dropdown (dari transaksi user)
        $tahunList = [];
        try {
            $db = \Database::getConnection();
            $stmt = $db->prepare("SELECT DISTINCT YEAR(tanggal) as y FROM transaksi WHERE input_by=? ORDER BY y DESC");
            $stmt->execute([$userId]);
            $tahunList = array_column($stmt->fetchAll(), 'y');
        } catch (\Throwable $e) { $tahunList = []; }
        if (empty($tahunList)) $tahunList = [(int)date('Y')];

        $pagination = ['page'=>$page,'perPage'=>$perPage,'total'=>$total,'totalPages'=>$totalPages,'baseUrl'=>base_url('seksi/transaksi')];

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
        $activePage = 'transaksicreate';
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

        // Cek apakah input berupa batch multi-baris (items)
        if (isset($_POST['items']) && is_array($_POST['items']) && count($_POST['items']) > 0) {
            $this->storeBatchItems($_POST, $db, $userId, $seksiId);
            return;
        }

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
            $activePage = 'transaksicreate';
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

        $jenisTransaksi = in_array($_POST['jenis_transaksi'] ?? '', ['perjalanan_dinas', 'belanja', 'honorarium', 'lainnya'], true)
            ? $_POST['jenis_transaksi']
            : 'lainnya';

        $id = $this->transaksiModel->createSeksi(
            $_POST['tanggal'],
            $seksiId,
            (int) $_POST['rekening_id'],
            trim($_POST['uraian']),
            (float) str_replace(['.', ','], '', $_POST['nilai']),
            trim($_POST['nomor_bukti']),
            $userId,
            !empty(trim($_POST['nama_penerima'] ?? '')) ? trim($_POST['nama_penerima']) : null,
            $jenisTransaksi,
            !empty(trim($_POST['nomor_surat_tugas'] ?? '')) ? trim($_POST['nomor_surat_tugas']) : null,
            !empty($_POST['tanggal_surat_tugas']) ? $_POST['tanggal_surat_tugas'] : null,
            !empty($_POST['tanggal_pelaksanaan']) ? $_POST['tanggal_pelaksanaan'] : null,
            !empty(trim($_POST['lokasi_kegiatan'] ?? '')) ? trim($_POST['lokasi_kegiatan']) : null,
            !empty($_POST['surat_tugas_ref_id']) ? (int) $_POST['surat_tugas_ref_id'] : null
        );

        $this->logAudit($userId, 'input_transaksi_seksi', 'transaksi', $id, 'Input transaksi oleh seksi menunggu verifikasi');
        $this->redirectWithMessage(base_url('seksi/transaksi'), 'success', 'Transaksi diajukan. Menunggu verifikasi admin.');
    }

    /**
     * Simpan batch item transaksi (misal dari multi-select Surat Tugas)
     */
    private function storeBatchItems(array $post, \PDO $db, int $userId, int $seksiId): void
    {
        $rekeningId = (int) ($post['rekening_id'] ?? 0);
        if (!$rekeningId || !$this->rekeningMilikSeksi($rekeningId, $seksiId, $db)) {
            $this->redirectWithMessage(base_url('seksi/transaksi/create'), 'error', 'Rekening belanja wajib dipilih dan harus valid untuk seksi Anda.');
            return;
        }

        $tanggal = !empty($post['tanggal']) ? $post['tanggal'] : date('Y-m-d');
        $items = $post['items'];
        $createdCount = 0;
        $errors = [];

        // Instansiasi model rincian biaya (reuse koneksi yang sama)
        $rincianBiayaModel = new \App\Models\RincianBiaya($db);

        foreach ($items as $idx => $item) {
            $nomorBukti   = trim($item['nomor_bukti'] ?? '');
            $uraian       = trim($item['uraian'] ?? '');
            $rawNilai     = str_replace(['.', ','], '', $item['nilai'] ?? '0');
            $nilai        = (float) $rawNilai;
            $namaPenerima = trim($item['nama_penerima'] ?? '');
            $pegawaiNip   = trim($item['pegawai_nip'] ?? '');

            // Proses rincian komponen terlebih dahulu jika ada
            $komponen = $item['komponen'] ?? [];
            $detailsValid = [];
            $totalRincian = 0;
            if (is_array($komponen)) {
                foreach ($komponen as $k) {
                    $namaK = trim($k['nama_komponen'] ?? '');
                    if ($namaK === '') continue;
                    $harga  = (float) str_replace(['.', ','], ['', '.'], $k['harga_satuan'] ?? '0');
                    $hari   = isset($k['jumlah_hari']) && $k['jumlah_hari'] !== '' ? (float) $k['jumlah_hari'] : null;
                    $jml    = (float) str_replace(['.', ','], ['', '.'], $k['jumlah'] ?? '0');
                    if ($jml <= 0) $jml = $hari !== null ? $harga * $hari : $harga;
                    $detailsValid[] = [
                        'nama_komponen' => $namaK,
                        'harga_satuan'  => $harga,
                        'jumlah_hari'   => $hari,
                        'jumlah'        => $jml,
                        'keterangan'    => trim($k['keterangan'] ?? '') ?: null,
                    ];
                    $totalRincian += $jml;
                }
            }

            // Jika nilai transaksi belum terisi atau 0 tapi ada total rincian, otomatis gunakan total rincian
            if ($nilai <= 0 && $totalRincian > 0) {
                $nilai = $totalRincian;
            }

            if ($nomorBukti === '' || $uraian === '' || $nilai <= 0) {
                $errors[] = "Baris #" . ($idx + 1) . " (" . ($namaPenerima ?: 'Penerima') . "): Nomor bukti, uraian, dan nilai harus valid.";
                continue;
            }

            // Ditetapkan sejumlah & dibayar semula: jika belum terisi, otomatis gunakan total rincian
            $ditetapkanRaw = (float) str_replace(['.', ','], ['', '.'], $item['ditetapkan_sejumlah'] ?? '0');
            $dibayarRaw    = (float) str_replace(['.', ','], ['', '.'], $item['dibayar_semula'] ?? '0');
            if ($ditetapkanRaw <= 0 && $totalRincian > 0) {
                $ditetapkanRaw = $totalRincian;
            }
            if ($dibayarRaw <= 0 && $totalRincian > 0) {
                $dibayarRaw = $totalRincian;
            }

            try {
                $id = $this->transaksiModel->createSeksi(
                    $tanggal,
                    $seksiId,
                    $rekeningId,
                    $uraian,
                    $nilai,
                    $nomorBukti,
                    $userId,
                    $namaPenerima ?: null,
                    'perjalanan_dinas',
                    !empty(trim($item['nomor_surat_tugas'] ?? '')) ? trim($item['nomor_surat_tugas']) : null,
                    !empty($item['tanggal_surat_tugas']) ? $item['tanggal_surat_tugas'] : null,
                    !empty($item['tanggal_pelaksanaan']) ? $item['tanggal_pelaksanaan'] : null,
                    !empty(trim($item['lokasi_kegiatan'] ?? '')) ? trim($item['lokasi_kegiatan']) : null,
                    !empty($item['surat_tugas_ref_id']) ? (int) $item['surat_tugas_ref_id'] : null,
                    $pegawaiNip ?: null
                );
                $this->logAudit($userId, 'input_transaksi_seksi', 'transaksi', $id, 'Input batch transaksi Surat Tugas an. ' . $namaPenerima);
                $createdCount++;

                if (!empty($detailsValid) && $pegawaiNip !== '') {
                    $stRefId    = !empty($item['surat_tugas_ref_id']) ? (int) $item['surat_tugas_ref_id'] : 0;
                    $nomorSurat = trim($item['nomor_surat_tugas'] ?? '');
                    $rincianBiayaModel->upsertDariTransaksi(
                        $id,
                        $stRefId,
                        $nomorSurat,
                        $pegawaiNip,
                        $namaPenerima ?: 'Pegawai',
                        null, // pangkat tidak tersedia di form batch
                        null, // jabatan tidak tersedia di form batch
                        $ditetapkanRaw,
                        $dibayarRaw,
                        trim($item['tempat_tanggal'] ?? '') ?: null,
                        $userId,
                        $detailsValid
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = "Gagal menyimpan baris #" . ($idx + 1) . ": " . $e->getMessage();
            }
        }

        if ($createdCount > 0) {
            $msg = "Berhasil mengajukan {$createdCount} transaksi perjalanan dinas.";
            if (!empty($errors)) {
                $msg .= " Catatan: " . implode(' ', $errors);
            }
            $this->redirectWithMessage(base_url('seksi/transaksi'), 'success', $msg);
        } else {
            $this->redirectWithMessage(base_url('seksi/transaksi/create'), 'error', 'Gagal menyimpan transaksi: ' . implode(' ', $errors));
        }
    }

    /**
     * Form edit transaksi oleh seksi (hanya status 'diajukan' & 'ditolak', diverifikasi terkunci).
     */
    public function edit(int $id): void
    {
        $this->requireSeksi();
        $db = \Database::getConnection();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $seksiId = $this->userSeksiId();

        $transaksi = $this->transaksiModel->getById($id);
        if (!$transaksi || (int) $transaksi['input_by'] !== $userId || !in_array($transaksi['status'] ?? '', ['diajukan','ditolak'], true)) {
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
     * Update transaksi oleh seksi (hanya status 'diajukan' & 'ditolak', diverifikasi terkunci).
     */
    public function update(int $id): void
    {
        $this->requireSeksi();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $seksiId = $this->userSeksiId();
        $db = \Database::getConnection();

        $existing = $this->transaksiModel->getById($id);
        if (!$existing || (int) $existing['input_by'] !== $userId || !in_array($existing['status'] ?? '', ['diajukan','ditolak'], true)) {
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

        $jenisTransaksi = in_array($_POST['jenis_transaksi'] ?? '', ['perjalanan_dinas', 'belanja', 'honorarium', 'lainnya'], true)
            ? $_POST['jenis_transaksi']
            : 'lainnya';

        $ok = $this->transaksiModel->updateSeksi(
            $id,
            $userId,
            $_POST['tanggal'],
            $seksiId,
            (int) $_POST['rekening_id'],
            trim($_POST['uraian']),
            (float) str_replace(['.', ','], '', $_POST['nilai']),
            trim($_POST['nomor_bukti']),
            !empty(trim($_POST['nama_penerima'] ?? '')) ? trim($_POST['nama_penerima']) : null,
            $jenisTransaksi,
            !empty(trim($_POST['nomor_surat_tugas'] ?? '')) ? trim($_POST['nomor_surat_tugas']) : null,
            !empty($_POST['tanggal_surat_tugas']) ? $_POST['tanggal_surat_tugas'] : null,
            !empty($_POST['tanggal_pelaksanaan']) ? $_POST['tanggal_pelaksanaan'] : null,
            !empty(trim($_POST['lokasi_kegiatan'] ?? '')) ? trim($_POST['lokasi_kegiatan']) : null,
            !empty($_POST['surat_tugas_ref_id']) ? (int) $_POST['surat_tugas_ref_id'] : null
        );

        $this->logAudit($userId, 'update_transaksi_seksi', 'transaksi', $id, $ok ? 'Update transaksi seksi' : 'Gagal update transaksi seksi');
        $this->redirectWithMessage(base_url('seksi/transaksi'), $ok ? 'success' : 'error', $ok ? 'Transaksi berhasil diperbarui' : 'Transaksi tidak dapat diperbarui');
    }

    /**
     * Delete transaksi oleh seksi (hanya status 'diajukan' & 'ditolak', diverifikasi terkunci).
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

    /**
     * AJAX: Generate nomor bukti otomatis dengan format 123.6.6/GU/nomor_urut/BULAN_ROMAWI/TAHUN
     */
    public function generateNomorBukti(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        $db = \Database::getConnection();

        $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
        $time = strtotime($tanggal);
        if (!$time) {
            $time = time();
        }

        $bulan = (int) date('m', $time);
        $tahun = (int) date('Y', $time);
        $countRequested = max(1, (int) ($_GET['count'] ?? 1));

        $romawiMap = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
        $bulanRomawi = $romawiMap[$bulan] ?? 'I';

        // Hitung transaksi yang sudah ada di bulan dan tahun tersebut
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM transaksi 
            WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        ");
        $stmt->execute([$bulan, $tahun]);
        $currentCount = (int) $stmt->fetchColumn();

        $generated = [];
        for ($i = 1; $i <= $countRequested; $i++) {
            $nomorUrut = $currentCount + $i;
            $generated[] = sprintf('123.6.6/GU/%d/%s/%d', $nomorUrut, $bulanRomawi, $tahun);
        }

        echo json_encode([
            'success' => true,
            'nomor_bukti' => $generated[0],
            'list' => $generated
        ]);
        exit;
    }

    /**
     * AJAX: Sisa pagu anggaran rekening untuk tahun transaksi (hanya dikurangi transaksi 'diverifikasi')
     */
    public function getSisaPagu(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        $db = \Database::getConnection();
        $seksiId = $this->userSeksiId();

        $rekeningId = (int) ($_GET['rekening_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? (int) date('Y'));

        if (!$rekeningId || !$this->rekeningMilikSeksi($rekeningId, $seksiId, $db)) {
            echo json_encode(['pagu' => null, 'sisa_pagu' => null]);
            exit;
        }

        // Pagu rekening tahun tsb
        $stmtPagu = $db->prepare("SELECT nilai_pagu FROM pagu WHERE rekening_id = ? AND tahun = ?");
        $stmtPagu->execute([$rekeningId, $tahun]);
        $paguVal = $stmtPagu->fetchColumn();

        if ($paguVal === false) {
            echo json_encode(['pagu' => null, 'sisa_pagu' => null, 'message' => 'Pagu belum ditetapkan']);
            exit;
        }

        $pagu = (float) $paguVal;
        $totalRealisasi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);
        $sisaPagu = $pagu - $totalRealisasi;

        echo json_encode([
            'pagu' => $pagu,
            'realisasi' => $totalRealisasi,
            'sisa_pagu' => $sisaPagu,
            'formatted_sisa' => 'Rp ' . number_format($sisaPagu, 0, ',', '.'),
            'formatted_pagu' => 'Rp ' . number_format($pagu, 0, ',', '.'),
        ]);
        exit;
    }

    /**
     * Download BKU (Buku Kas Umum) seksi dalam format Excel.
     *
     * - Mencakup SEMUA status transaksi (diajukan, diverifikasi, ditolak).
     * - Filter bulan & tahun wajib diisi.
     * - Kolom Saldo (running balance) hanya ditampilkan jika role = 'admin'.
     *   Role seksi (tu/rlpm/tkuk) tidak mendapat kolom Saldo.
     * - Format kolom: No, Tanggal, Uraian, No Bukti, Pengeluaran, Status [, Saldo jika admin].
     */
    public function downloadBku(): void
    {
        $this->requireSeksi();

        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : null;

        if ($bulan === null || $tahun === null || $bulan < 1 || $bulan > 12 || $tahun < 2000) {
            http_response_code(400);
            echo 'Pilih Bulan dan Tahun terlebih dahulu untuk mengunduh BKU';
            exit;
        }

        // Tentukan apakah user berhak melihat kolom Saldo
        // Hanya role 'admin' yang mendapat kolom Saldo (running balance)
        $userRole    = $_SESSION['role'] ?? '';
        $tampilSaldo = ($userRole === 'admin');

        $seksiId = $this->userSeksiId();
        $db      = \Database::getConnection();

        // Ambil nama seksi
        $stmtSeksi = $db->prepare('SELECT nama_seksi FROM seksi WHERE id = ?');
        $stmtSeksi->execute([$seksiId]);
        $namaSeksi = $stmtSeksi->fetchColumn() ?: 'Seksi';

        // Ambil SEMUA transaksi seksi ini pada bulan/tahun, urut tanggal ASC lalu id ASC
        $stmt = $db->prepare("
            SELECT t.tanggal, t.uraian, t.nomor_bukti, t.nilai, t.nama_penerima, t.status
            FROM transaksi t
            WHERE t.seksi_id = :seksi_id
              AND MONTH(t.tanggal) = :bulan
              AND YEAR(t.tanggal)  = :tahun
            ORDER BY t.tanggal ASC, t.id ASC
        ");
        $stmt->execute([':seksi_id' => $seksiId, ':bulan' => $bulan, ':tahun' => $tahun]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Label status Indonesia
        $statusLabel = [
            'diajukan'     => 'Menunggu Verifikasi',
            'diverifikasi' => 'Diverifikasi',
            'ditolak'      => 'Ditolak',
        ];

        // Nama bulan Indonesia
        $namaBulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',    4 => 'April',
            5 => 'Mei',     6 => 'Juni',     7 => 'Juli',      8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $namaBulan = $namaBulanMap[$bulan] ?? (string) $bulan;

        // ── Tentukan kolom sesuai role ─────────────────────────────────────
        // Tanpa saldo : A=No, B=Tanggal, C=Uraian, D=No Bukti, E=Pengeluaran, F=Status
        // Dengan saldo: A=No, B=Tanggal, C=Uraian, D=No Bukti, E=Pengeluaran, F=Status, G=Saldo
        $lastCol      = $tampilSaldo ? 'G' : 'F';
        $totalCols    = $tampilSaldo ? 7  : 6;
        $colLetters   = ['A','B','C','D','E','F','G'];

        // ── Generate Excel ─────────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BKU');

        // Header organisasi (baris 1–3)
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', 'BUKU KAS UMUM (BKU)');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->setCellValue('A2', strtoupper($namaSeksi));
        $sheet->mergeCells('A3:' . $lastCol . '3');
        $sheet->setCellValue('A3', 'Bulan: ' . $namaBulan . ' ' . $tahun);

        foreach (['A1', 'A2', 'A3'] as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Header kolom (baris 5)
        $headers = ['No', 'Tanggal', 'Uraian / Keterangan', 'No Bukti', 'Pengeluaran (Rp)', 'Status'];
        if ($tampilSaldo) {
            $headers[] = 'Saldo (Rp)';
        }

        foreach ($headers as $i => $h) {
            $sheet->setCellValue($colLetters[$i] . '5', $h);
        }

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ];
        $sheet->getStyle('A5:' . $lastCol . '5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(24);

        // ── Baris data ────────────────────────────────────────────────────
        $dataRow   = 6;
        $no        = 1;
        $saldo     = 0.0;

        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ];
        $altFill = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ];

        // Warna latar per status (untuk kolom Status)
        $statusFill = [
            'diajukan'     => ['rgb' => 'FEF9C3'], // kuning muda
            'diverifikasi' => ['rgb' => 'DCFCE7'], // hijau muda
            'ditolak'      => ['rgb' => 'FEE2E2'], // merah muda
        ];
        $statusColor = [
            'diajukan'     => ['rgb' => '854D0E'],
            'diverifikasi' => ['rgb' => '166534'],
            'ditolak'      => ['rgb' => '991B1B'],
        ];

        foreach ($rows as $t) {
            $nilai        = (float) ($t['nilai'] ?? 0);
            $statusKey    = $t['status'] ?? '';
            $uraianTampil = $t['uraian'] ?? '';
            if (!empty($t['nama_penerima'])) {
                $uraianTampil .= "\na.n. " . $t['nama_penerima'];
            }

            // Saldo hanya dihitung jika transaksi sudah diverifikasi (supaya running balance bermakna)
            if ($tampilSaldo && $statusKey === 'diverifikasi') {
                $saldo += $nilai;
            }

            $sheet->setCellValue('A' . $dataRow, $no);
            $sheet->setCellValue('B' . $dataRow, date('d/m/Y', strtotime($t['tanggal'])));
            $sheet->setCellValue('C' . $dataRow, $uraianTampil);
            $sheet->setCellValue('D' . $dataRow, $t['nomor_bukti'] ?? '-');
            $sheet->setCellValue('E' . $dataRow, $nilai);
            $sheet->setCellValue('F' . $dataRow, $statusLabel[$statusKey] ?? ucfirst($statusKey));
            if ($tampilSaldo) {
                // Saldo ditampilkan hanya pada baris diverifikasi, baris lain kosong
                if ($statusKey === 'diverifikasi') {
                    $sheet->setCellValue('G' . $dataRow, $saldo);
                    $sheet->getStyle('G' . $dataRow)->getNumberFormat()->setFormatCode('#,##0');
                } else {
                    $sheet->setCellValue('G' . $dataRow, '-');
                }
                $sheet->getStyle('G' . $dataRow)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }

            $sheet->getStyle('E' . $dataRow)->getNumberFormat()->setFormatCode('#,##0');

            // Border seluruh baris
            $sheet->getStyle('A' . $dataRow . ':' . $lastCol . $dataRow)->applyFromArray($borderStyle);

            // Zebra stripe
            if ($no % 2 === 0) {
                $sheet->getStyle('A' . $dataRow . ':' . $lastCol . $dataRow)->applyFromArray($altFill);
            }

            // Warna kolom status
            if (isset($statusFill[$statusKey])) {
                $sheet->getStyle('F' . $dataRow)->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => $statusFill[$statusKey]],
                    'font' => ['bold' => true, 'color' => $statusColor[$statusKey]],
                ]);
            }

            // Alignment per kolom
            $sheet->getStyle('A' . $dataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $dataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $dataRow)->getAlignment()->setWrapText(true);
            $sheet->getStyle('D' . $dataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $dataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('F' . $dataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $dataRow++;
            $no++;
        }

        // ── Baris total / kosong ───────────────────────────────────────────
        if (count($rows) === 0) {
            $sheet->mergeCells('A6:' . $lastCol . '6');
            $sheet->setCellValue('A6', 'Tidak ada transaksi pada periode ini.');
            $sheet->getStyle('A6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A6')->getFont()->setItalic(true);
            $sheet->getStyle('A6')->getFont()->getColor()->setRGB('64748B');
        } else {
            $totalRow = $dataRow;
            // Merge kolom label (A–D) dan isi total pengeluaran di E
            $sheet->mergeCells('A' . $totalRow . ':D' . $totalRow);
            $sheet->setCellValue('A' . $totalRow, 'TOTAL PENGELUARAN');

            // Total pengeluaran = semua nilai tanpa memandang status (termasuk pending & ditolak untuk transparansi)
            $totalNilai = array_sum(array_column($rows, 'nilai'));
            $sheet->setCellValue('E' . $totalRow, (float) $totalNilai);
            $sheet->getStyle('E' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

            if ($tampilSaldo) {
                // Total saldo akhir (hanya dari diverifikasi)
                $sheet->setCellValue('G' . $totalRow, $saldo);
                $sheet->getStyle('G' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('G' . $totalRow)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }

            $totalStyle = [
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']]],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
            ];
            $sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->applyFromArray($totalStyle);
            $sheet->getStyle('A' . $totalRow)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // ── Lebar kolom ───────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(45);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(24);
        if ($tampilSaldo) {
            $sheet->getColumnDimension('G')->setWidth(20);
        }

        // ── Nama file & kirim ke browser ──────────────────────────────────
        $namaSeksiFile = preg_replace('/[^A-Za-z0-9\s\-]/', '', $namaSeksi);
        $namaSeksiFile = preg_replace('/\s+/', '_', trim($namaSeksiFile));
        $fileName = 'BKU_' . $namaSeksiFile . '_' . $namaBulan . '_' . $tahun . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Unduh Excel Rincian Biaya untuk satu transaksi perjalanan dinas (per pegawai).
     * Akses: GET /seksi/transaksi/unduh-rincian-biaya?transaksi_id=X
     */
    public function downloadRincianBiaya(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }

        $userId      = (int) ($_SESSION['user_id'] ?? 0);
        $isAdmin     = !empty($_SESSION['is_admin']);
        $transaksiId = (int) ($_GET['transaksi_id'] ?? 0);

        if (!$transaksiId) {
            http_response_code(400);
            echo 'ID Transaksi tidak valid.';
            exit;
        }

        $db = \Database::getConnection();

        // Admin bisa unduh semua, seksi hanya transaksi miliknya
        if ($isAdmin) {
            $stmtT = $db->prepare("SELECT * FROM transaksi WHERE id = ? LIMIT 1");
            $stmtT->execute([$transaksiId]);
        } else {
            $stmtT = $db->prepare("SELECT * FROM transaksi WHERE id = ? AND input_by = ? LIMIT 1");
            $stmtT->execute([$transaksiId, $userId]);
        }

        $transaksi = $stmtT->fetch(\PDO::FETCH_ASSOC);
        if (!$transaksi) {
            http_response_code(403);
            echo 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.';
            exit;
        }

        require_once __DIR__ . '/../../app/Models/RincianBiaya.php';
        require_once __DIR__ . '/../Services/RincianBiayaExportService.php';

        $rincianModel = new \App\Models\RincianBiaya($db);
        $data = $rincianModel->getByTransaksiId($transaksiId);

        if (!$data) {
            http_response_code(404);
            echo 'Rincian biaya untuk transaksi ini belum diisi.';
            exit;
        }

        $header  = $data['header'];
        $details = $data['details'];

        $exportService = new \App\Services\RincianBiayaExportService();
        $exportService->download($header, $details, $transaksi);
    }

    /**
     * AJAX: Cari Surat Tugas dari db_surat_tugas (Read-only)
     */
    public function searchSuratTugas(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../config/database_surat_tugas.php';

        $pdo = \DatabaseSuratTugas::getConnection();
        if (!$pdo) {
            echo json_encode([
                'success' => false,
                'message' => 'Layanan Database Surat Tugas sedang tidak dapat diakses.'
            ]);
            exit;
        }

        try {
            $q = trim($_GET['q'] ?? '');
            $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
            $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : null;

            $conditions = [];
            $params = [];

            if ($q !== '') {
                $conditions[] = '(nomor_surat LIKE :kw OR untuk LIKE :kw)';
                $params[':kw'] = '%' . $q . '%';
            }

            if ($bulan !== null && $bulan >= 1 && $bulan <= 12) {
                $conditions[] = 'MONTH(tanggal_mulai) = :bulan';
                $params[':bulan'] = $bulan;
            }

            if ($tahun !== null && $tahun > 2000) {
                $conditions[] = 'YEAR(tanggal_mulai) = :tahun';
                $params[':tahun'] = $tahun;
            }

            $whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $stmt = $pdo->prepare("
                SELECT id, nomor_surat, tanggal_surat, untuk, tanggal_mulai, tanggal_selesai, dasar_surat
                FROM surat_tugas
                {$whereSql}
                ORDER BY tanggal_mulai DESC, id DESC
                LIMIT 50
            ");

            foreach ($params as $key => $val) {
                if (is_int($val)) {
                    $stmt->bindValue($key, $val, \PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val, \PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            $results = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Throwable $e) {
            error_log('Error searchSuratTugas: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mencari surat tugas: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * AJAX: Ambil daftar Pegawai untuk Surat Tugas tertentu (Read-only)
     * Menggunakan TRIM & collation-safe join untuk menangani spasi/format NIP yang tidak konsisten
     */
    public function getPegawaiSuratTugas(): void
    {
        $this->requireSeksi();
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../config/database_surat_tugas.php';

        $pdo = \DatabaseSuratTugas::getConnection();
        if (!$pdo) {
            echo json_encode([
                'success' => false,
                'message' => 'Layanan Database Surat Tugas sedang tidak dapat diakses.'
            ]);
            exit;
        }

        $idST = (int) ($_GET['id_surat_tugas'] ?? 0);
        if (!$idST) {
            echo json_encode(['success' => false, 'message' => 'ID Surat Tugas wajib disertakan.']);
            exit;
        }

        try {
            // LEFT JOIN dengan normalisasi spasi agar semua pegawai tetap muncul meski NIP berformat beda
            $stmt = $pdo->prepare("
                SELECT 
                    pt.nip AS pt_nip,
                    COALESCE(p.nip, pt.nip) AS nip,
                    COALESCE(p.nama, pt.nip, 'Pegawai') AS nama,
                    p.pangkat,
                    p.jabatan,
                    pt.urutan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip 
                    OR REPLACE(p.nip, ' ', '') = REPLACE(pt.nip, ' ', '')
                )
                WHERE pt.id_surat_tugas = :id_surat_tugas
                ORDER BY pt.urutan ASC, p.nama ASC
            ");
            $stmt->bindParam(':id_surat_tugas', $idST, \PDO::PARAM_INT);
            $stmt->execute();
            $pegawais = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $pegawais
            ]);
        } catch (\Throwable $e) {
            error_log('Error getPegawaiSuratTugas: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Gagal memuat pegawai: ' . $e->getMessage()
            ]);
        }
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

        $jenis = $data['jenis_transaksi'] ?? 'lainnya';
        if ($jenis === 'perjalanan_dinas') {
            if (empty(trim($data['nomor_surat_tugas'] ?? ''))) {
                $errors[] = 'Nomor Surat Tugas wajib diisi untuk jenis Perjalanan Dinas';
            }
            if (empty($data['tanggal_surat_tugas'])) {
                $errors[] = 'Tanggal Surat Tugas wajib diisi untuk jenis Perjalanan Dinas';
            } elseif (!strtotime($data['tanggal_surat_tugas'])) {
                $errors[] = 'Format Tanggal Surat Tugas tidak valid';
            }
            if (empty($data['tanggal_pelaksanaan'])) {
                $errors[] = 'Tanggal Pelaksanaan wajib diisi untuk jenis Perjalanan Dinas';
            } elseif (!strtotime($data['tanggal_pelaksanaan'])) {
                $errors[] = 'Format Tanggal Pelaksanaan tidak valid';
            }
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
