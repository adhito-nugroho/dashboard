<?php

namespace App\Controllers;

use App\Models\KalibrasiKuitansiElemen;
use App\Models\PrinterKuitansi;
use App\Models\Transaksi;
use App\Services\KuitansiPdfService;
use PDO;

class KuitansiController
{
    private KalibrasiKuitansiElemen $elemen;
    private PrinterKuitansi $printer;
    private Transaksi $transaksi;
    private KuitansiPdfService $pdf;

    public const REF_DIR = __DIR__ . '/../../public/uploads/kalibrasi';
    public const REF_BASE = 'referensi_kuitansi';
    public const DEFAULT_SUMATRA_PATH = 'C:\\Program Files\\SumatraPDF\\SumatraPDF.exe';

    /** Lokasi file executable SumatraPDF (configurable via .env SUMATRAPDF_PATH) */
    public function sumatraPdfPath(): string
    {
        return (string) ($_ENV['SUMATRAPDF_PATH'] ?? getenv('SUMATRAPDF_PATH') ?: self::DEFAULT_SUMATRA_PATH);
    }

    public function __construct(PDO $db)
    {
        $this->elemen = new KalibrasiKuitansiElemen($db);
        $this->printer = new PrinterKuitansi($db);
        $this->transaksi = new Transaksi($db);
        $this->pdf = new KuitansiPdfService();
    }

    private function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    /**
     * Kalibrasi boleh diakses admin pusat maupun admin seksi (rlpm/tkuk/tu/seksi).
     * Kalibrasi tersimpan PER PRINTER (tabel printer_kuitansi) karena tiap
     * printer punya offset mekanis berbeda.
     */
    private function requireKalibrasiAccess(): void
    {
        $this->requireLogin();
        $isAdmin = !empty($_SESSION['is_admin']);
        $isSeksi = in_array($_SESSION['role'] ?? '', ['rlpm', 'tkuk', 'tu', 'seksi'], true);
        if (!($isAdmin || $isSeksi)) {
            http_response_code(403);
            echo 'Akses ditolak: halaman kalibrasi hanya untuk admin.';
            exit;
        }
    }

    private function actor(): string
    {
        return (string) ($_SESSION['username'] ?? ('user#' . ($_SESSION['user_id'] ?? '?')));
    }

    /** Posisi aktif satu printer: default config di-merge dengan DB (DB menang). */
    private function activePositions(int $printerId): array
    {
        $this->pdf->setPositions($this->elemen->getAll($printerId));
        return $this->pdf->getPositions();
    }

    /** Printer yang diminta bila ada, selain itu printer default. */
    private function resolvePrinterId(?int $requested): int
    {
        if ($requested !== null && $requested > 0 && $this->printer->get($requested) !== null) {
            return $requested;
        }
        return $this->printer->getDefaultId();
    }

    /**
     * File referensi aktif: gambar (jpg/jpeg/png) atau PDF scan.
     * Bila keduanya ada, yang terbaru (mtime) yang dipakai.
     * Return ['url'=>..., 'kind'=>'image'|'pdf'] atau null.
     */
    public static function referenceInfo(): ?array
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

    public static function referenceUrl(): ?string
    {
        $info = self::referenceInfo();
        return $info['url'] ?? null;
    }

    /**
     * Editor visual kalibrasi per-elemen (admin pusat + admin seksi).
     */
    public function kalibrasi(): void
    {
        $this->requireKalibrasiAccess();

        $printers = $this->printer->getAll();
        $reqPid = isset($_GET['printer']) ? (int) $_GET['printer'] : null;
        $printerId = $this->resolvePrinterId($reqPid);

        // Contoh data asli: transaksi pilihan (?transaksi=) atau terbaru dalam lingkup.
        // Seksi hanya boleh memakai transaksi seksinya sendiri.
        $isAdmin = !empty($_SESSION['is_admin']);
        $scopeSeksi = $isAdmin ? null : (int) ($_SESSION['seksi_id'] ?? 0);
        $trxList = $this->transaksi->listRecentForPicker($scopeSeksi, 50);
        $listIds = array_map(fn($t) => (int) $t['id'], $trxList);
        $reqTrx = isset($_GET['transaksi']) ? (int) $_GET['transaksi'] : 0;
        $sampleId = in_array($reqTrx, $listIds, true) ? $reqTrx : (int) ($listIds[0] ?? 0);
        $sampleTexts = [];
        $sampleLabel = null;
        if ($sampleId > 0) {
            $full = $this->transaksi->getById($sampleId);
            if (is_array($full) && ($isAdmin || (int) ($full['seksi_id'] ?? 0) === $scopeSeksi)) {
                $sampleTexts = $this->pdf->sampleTexts($full);
                $sampleLabel = '#' . $sampleId . ' — ' . ($full['nomor_bukti'] ?? '');
            } else {
                $sampleId = 0;
            }
        }

        $positions = $this->activePositions($printerId);
        $labels = [];
        try {
            foreach ($this->elemen->getAll($printerId) as $k => $v) {
                $labels[$k] = $v['label'] ?? $k;
            }
        } catch (\Throwable $e) {
        }

        // Lebar kotak kanvas: max_width_mm (uraian), w_mm config (field), atau peta ttd_widths.
        $koord = (new KuitansiPdfService())->getKoordinat();
        $ttdW = $koord['ttd_widths'] ?? [];
        $widths = [];
        foreach (KuitansiPdfService::ELEMEN_KEYS as $k) {
            if ($k === 'uraian') {
                $widths[$k] = (float) ($positions['uraian']['max_width_mm'] ?? $koord['uraian']['w_mm'] ?? 170);
            } elseif (isset($ttdW[$k])) {
                $widths[$k] = (float) $ttdW[$k];
            } elseif (isset($koord[$k]['w_mm'])) {
                $widths[$k] = (float) $koord[$k]['w_mm'];
            } else {
                $widths[$k] = 60.0;
            }
        }

        $flash = $_SESSION['flash_message'] ?? null;
        $flashType = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);

        $pageTitle = 'Kalibrasi Cetak Kuitansi';
        $activePage = 'kalibrasi_kuitansi';
        $viewFile = __DIR__ . '/../../views/kuitansi/kalibrasi.php';
        $refInfo = self::referenceInfo();
        $kalibrasiData = [
            'positions' => $positions,
            'labels' => $labels,
            'widths' => $widths,
            'refUrl' => $refInfo['url'] ?? null,
            'refKind' => $refInfo['kind'] ?? null,
            'flash' => $flash,
            'flashType' => $flashType,
            'printers' => $printers,
            'printerId' => $printerId,
            'trxList' => $trxList,
            'sampleId' => $sampleId,
            'sampleTexts' => $sampleTexts,
            'sampleLabel' => $sampleLabel,
        ];
        // Admin pusat pakai layout admin, admin seksi pakai layout seksi.
        $layout = !empty($_SESSION['is_admin'])
            ? __DIR__ . '/../../views/layout.php'
            : __DIR__ . '/../../views/layout_seksi.php';
        include $layout;
    }

    /**
     * Simpan posisi semua elemen SATU printer (fetch JSON dari kanvas).
     * Body: {"printer_id":1,"items":[{"elemen_key","x_mm","y_mm","max_width_mm"|null,"label"}, ...]}
     */
    public function simpanElemen(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode((string) file_get_contents('php://input'), true);
        $printerId = $this->resolvePrinterId(isset($body['printer_id']) ? (int) $body['printer_id'] : null);
        if ($printerId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Printer tidak dikenal. Tambahkan printer dulu.']);
            return;
        }
        $items = is_array($body['items'] ?? null) ? $body['items'] : null;
        if (!is_array($items) || $items === []) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Payload kosong: kirim items[] posisi elemen.']);
            return;
        }
        // Hanya key yang dikenal generator.
        $allowed = KuitansiPdfService::ELEMEN_KEYS;
        $items = array_values(array_filter($items, fn($it) => in_array((string) ($it['elemen_key'] ?? ''), $allowed, true)));
        if ($items === []) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Tidak ada elemen_key yang dikenal.']);
            return;
        }

        $count = $this->elemen->saveAll($printerId, $items, $this->actor());
        echo json_encode(['ok' => true, 'saved' => $count, 'printer_id' => $printerId]);
    }

    /**
     * Cetak Uji (fetch dari kanvas): posisi SEMUA elemen saat ini di client,
     * belum tentu tersimpan. Body: {"positions":{"key":{"x_mm","y_mm","max_width_mm"}}}
     * Render dummy crosshair — bukan data transaksi asli.
     */
    public function cetakUji(): void
    {
        $this->requireKalibrasiAccess();
        $body = json_decode((string) file_get_contents('php://input'), true);
        $positions = is_array($body['positions'] ?? null) ? $body['positions'] : [];
        $this->pdf->streamTestPage($positions);
        exit;
    }

    /**
     * Upload contoh kertas NCR kosong sebagai background kanvas.
     * - Gambar (JPG/PNG, maks 5MB): langsung jadi background.
     * - PDF scan (maks 10MB): disimpan, halaman 1 di-render di browser via PDF.js.
     *   Scan flatbed tanpa perspektif + dipangkas tepat di tepi kertas memberi
     *   hasil paling akurat. Tiap jenis overwrite file sejenisnya; gambar dan
     *   PDF boleh berdampingan — yang terbaru yang ditampilkan.
     */
    public function uploadReferensi(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $f = $_FILES['referensi'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Pilih file JPG/PNG/PDF terlebih dahulu.']);
            return;
        }

        // Deteksi jenis via isi file (bukan ekstensi).
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
            $info = self::referenceInfo();
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
        // Hapus gambar lama (semua ekstensi gambar) agar selalu 1 gambar aktif.
        // File PDF dibiarkan — yang terbaru (gambar/pdf) yang ditampilkan.
        foreach (glob(self::REF_DIR . '/' . self::REF_BASE . '.{jpg,jpeg,png}', GLOB_BRACE) ?: [] as $old) {
            @unlink($old);
        }
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $dest = self::REF_DIR . '/' . self::REF_BASE . '.' . $ext;
        if (!move_uploaded_file($tmp, $dest)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan file di server.']);
            return;
        }

        $info = self::referenceInfo();
        echo json_encode(['ok' => true, 'url' => $info['url'] ?? null, 'kind' => 'image']);
    }

    /**
     * Tambah printer (form POST: nama, keterangan). Posisi awal disalin dari
     * printer default agar tidak mulai dari nol. Redirect kembali ke kanvas
     * printer baru.
     */
    public function tambahPrinter(): void
    {
        $this->requireKalibrasiAccess();
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $ket = trim((string) ($_POST['keterangan'] ?? ''));
        $winPrinter = trim((string) ($_POST['windows_printer_name'] ?? ''));
        $paperForm = trim((string) ($_POST['paper_form_name'] ?? ''));
        if ($paperForm === '') {
            $paperForm = 'Kuitansi';
        }

        if ($nama === '') {
            $_SESSION['flash_message'] = 'Nama printer wajib diisi.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('kuitansi/kalibrasi'));
            exit;
        }
        $sourceId = $this->printer->getDefaultId();
        $source = $sourceId > 0 ? $this->elemen->getAll($sourceId) : [];
        $newId = $this->printer->create(
            $nama,
            $ket !== '' ? $ket : null,
            $source,
            $winPrinter !== '' ? $winPrinter : null,
            $paperForm
        );
        if ($newId <= 0) {
            $_SESSION['flash_message'] = 'Gagal menambah printer (nama duplikat?).';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('kuitansi/kalibrasi'));
            exit;
        }
        $_SESSION['flash_message'] = 'Printer "' . $nama . '" ditambahkan; posisi awal disalin dari printer default. Silakan Cetak Uji dan sesuaikan.';
        $_SESSION['flash_type'] = 'success';
        header('Location: ' . base_url('kuitansi/kalibrasi?printer=' . $newId));
        exit;
    }

    /** Update nama Windows printer & nama paper form (form POST: id, windows_printer_name, paper_form_name). */
    public function updatePrinterSettings(): void
    {
        $this->requireKalibrasiAccess();
        $id = (int) ($_POST['id'] ?? 0);
        $winName = trim((string) ($_POST['windows_printer_name'] ?? ''));
        $paperForm = trim((string) ($_POST['paper_form_name'] ?? ''));
        if ($paperForm === '') {
            $paperForm = 'Kuitansi';
        }

        if ($this->printer->updateWindowsSettings($id, $winName !== '' ? $winName : null, $paperForm)) {
            $_SESSION['flash_message'] = 'Pengaturan printer Windows berhasil disimpan.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Gagal menyimpan pengaturan printer Windows.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: ' . base_url('kuitansi/kalibrasi?printer=' . $id));
        exit;
    }

    /** Jadikan printer default untuk tombol Download (form POST: id). */
    public function setDefaultPrinter(): void
    {
        $this->requireKalibrasiAccess();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->printer->setDefault($id)) {
            $_SESSION['flash_message'] = 'Printer tidak ditemukan.';
            $_SESSION['flash_type'] = 'error';
        } else {
            $_SESSION['flash_message'] = 'Printer default diubah. Tombol Download kini memakai kalibrasi printer ini.';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: ' . base_url('kuitansi/kalibrasi?printer=' . $id));
        exit;
    }

    /** Hapus printer non-default beserta kalibrasinya (form POST: id). */
    public function hapusPrinter(): void
    {
        $this->requireKalibrasiAccess();
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->printer->delete($id)) {
            $_SESSION['flash_message'] = 'Gagal menghapus (printer default tidak boleh dihapus).';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('kuitansi/kalibrasi?printer=' . $id));
            exit;
        }
        $_SESSION['flash_message'] = 'Printer dihapus.';
        $_SESSION['flash_type'] = 'success';
        header('Location: ' . base_url('kuitansi/kalibrasi'));
        exit;
    }

    /**
     * Cetak kuitansi satu transaksi (tombol download hijau di kolom Aksi — tidak diubah).
     * Memakai kalibrasi PRINTER DEFAULT; override via ?printer_id= bila perlu.
     * ISI teks sama; alur verifikasi tidak disentuh.
     */
    public function cetak(int $id): void
    {
        $this->requireLogin();

        $trx = $this->transaksi->getById($id);
        if ($trx === null) {
            http_response_code(404);
            echo 'Transaksi tidak ditemukan.';
            exit;
        }

        $isAdmin = !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            $role = $_SESSION['role'] ?? '';
            $allowedRoles = ['rlpm', 'tkuk', 'tu', 'seksi'];
            if (!in_array($role, $allowedRoles, true)) {
                http_response_code(403);
                echo 'Akses ditolak.';
                exit;
            }
            $mySeksi = (int) ($_SESSION['seksi_id'] ?? 0);
            if ($mySeksi > 0 && (int) ($trx['seksi_id'] ?? 0) !== $mySeksi) {
                http_response_code(403);
                echo 'Akses ditolak: bukan transaksi seksi Anda.';
                exit;
            }
        }

        $printerId = $this->resolvePrinterId(isset($_GET['printer_id']) ? (int) $_GET['printer_id'] : null);
        $this->pdf->setPositions($printerId > 0 ? $this->elemen->getAll($printerId) : []);
        $this->pdf->streamKuitansi($trx);
        exit;
    }

    /**
     * Cetak langsung kuitansi satu transaksi ke printer fisik via SumatraPDF (silent print).
     * Endpoint: POST /kuitansi/{id}/cetak-langsung
     * Otorisasi sama persis dengan cetak().
     * Return JSON: {ok: bool, message: string}
     */
    public function cetakLangsung(int $id): void
    {
        $this->requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $trx = $this->transaksi->getById($id);
        if ($trx === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Transaksi #' . $id . ' tidak ditemukan.']);
            exit;
        }

        $isAdmin = !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            $role = $_SESSION['role'] ?? '';
            $allowedRoles = ['rlpm', 'tkuk', 'tu', 'seksi'];
            if (!in_array($role, $allowedRoles, true)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Akses ditolak.']);
                exit;
            }
            $mySeksi = (int) ($_SESSION['seksi_id'] ?? 0);
            if ($mySeksi > 0 && (int) ($trx['seksi_id'] ?? 0) !== $mySeksi) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Akses ditolak: bukan transaksi seksi Anda.']);
                exit;
            }
        }

        $printerId = $this->resolvePrinterId(isset($_GET['printer_id']) ? (int) $_GET['printer_id'] : null);
        $printerData = $this->printer->get($printerId);
        if ($printerData === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Profil printer tidak ditemukan di database.']);
            exit;
        }

        $winPrinter = trim((string) ($printerData['windows_printer_name'] ?? ''));
        if ($winPrinter === '') {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'Printer "' . ($printerData['nama'] ?? 'Default') . '" belum memiliki nama Windows printer (windows_printer_name). Silakan atur di menu Kalibrasi Kuitansi terlebih dahulu.'
            ]);
            exit;
        }

        $paperForm = trim((string) ($printerData['paper_form_name'] ?? ''));
        if ($paperForm === '') {
            $paperForm = 'Kuitansi';
        }

        $sumatraBin = $this->sumatraPdfPath();
        if (!file_exists($sumatraBin)) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Executable SumatraPDF tidak ditemukan di "' . $sumatraBin . '". Pastikan SumatraPDF terpasang atau atur SUMATRAPDF_PATH di file config/.env.'
            ]);
            exit;
        }

        // Simpan PDF kuitansi ke file sementara di sys_get_temp_dir()
        $tempDir = rtrim(sys_get_temp_dir(), '\\/');
        $tempPdf = $tempDir . DIRECTORY_SEPARATOR . 'kuitansi_direct_' . $id . '_' . uniqid() . '.pdf';

        try {
            $this->pdf->setPositions($printerId > 0 ? $this->elemen->getAll($printerId) : []);
            $this->pdf->savePdfKuitansi($trx, $tempPdf);
        } catch (\Throwable $e) {
            error_log('cetakLangsung savePdf error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal membuat file PDF kuitansi: ' . $e->getMessage()]);
            exit;
        }

        if (!file_exists($tempPdf)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal membuat file PDF kuitansi sementara.']);
            exit;
        }

        // Hapus file sementara saat proses request PHP selesai (dengan jeda 500ms agar spooler selesai membaca)
        register_shutdown_function(function () use ($tempPdf) {
            if (file_exists($tempPdf)) {
                usleep(500000);
                @unlink($tempPdf);
            }
        });

        // Eksekusi SumatraPDF
        // Format perintah: "C:\path\SumatraPDF.exe" -print-to "Printer Name" -print-settings "landscape,paper=Kuitansi,noscale" -silent "C:\temp\file.pdf"
        $printSettings = "landscape,paper={$paperForm},noscale";
        $cmd = sprintf(
            '"%s" -print-to "%s" -print-settings "%s" -silent "%s"',
            $sumatraBin,
            $winPrinter,
            $printSettings,
            $tempPdf
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            error_log('SumatraPDF exec failed: cmd=' . $cmd . ' code=' . $returnCode . ' output=' . implode("\n", $output));
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Perintah cetak gagal dieksekusi oleh SumatraPDF (kode: ' . $returnCode . '). Pastikan nama printer "' . $winPrinter . '" terpasang di Windows dan printer dalam kondisi aktif.'
            ]);
            exit;
        }

        $noBukti = !empty($trx['nomor_bukti']) ? ' (' . $trx['nomor_bukti'] . ')' : '';
        echo json_encode([
            'ok' => true,
            'message' => 'Kuitansi transaksi #' . $id . $noBukti . ' berhasil dikirim ke printer "' . $winPrinter . '" [Form: ' . $paperForm . '].'
        ]);
        exit;
    }
}
