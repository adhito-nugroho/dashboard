<?php

namespace App\Controllers;

use App\Models\KalibrasiKuitansiElemen;
use App\Models\Transaksi;
use App\Services\KuitansiPdfService;
use PDO;

class KuitansiController
{
    private KalibrasiKuitansiElemen $elemen;
    private Transaksi $transaksi;
    private KuitansiPdfService $pdf;

    public const REF_DIR = __DIR__ . '/../../public/uploads/kalibrasi';
    public const REF_BASE = 'referensi_kuitansi';

    public function __construct(PDO $db)
    {
        $this->elemen = new KalibrasiKuitansiElemen($db);
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
     * Catatan: tabel kalibrasi satu untuk semua (1 printer), jadi perubahan
     * oleh satu seksi berlaku untuk semua.
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

    /** Posisi aktif: default config di-merge dengan DB (DB menang). */
    private function activePositions(): array
    {
        $this->pdf->setPositions($this->elemen->getAll());
        return $this->pdf->getPositions();
    }

    public static function referenceUrl(): ?string
    {
        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            $file = self::REF_DIR . '/' . self::REF_BASE . '.' . $ext;
            if (is_file($file)) {
                return base_url('uploads/kalibrasi/' . self::REF_BASE . '.' . $ext) . '?v=' . filemtime($file);
            }
        }
        return null;
    }

    /**
     * Editor visual kalibrasi per-elemen (admin pusat + admin seksi).
     */
    public function kalibrasi(): void
    {
        $this->requireKalibrasiAccess();

        $positions = $this->activePositions();
        $labels = [];
        try {
            foreach ($this->elemen->getAll() as $k => $v) {
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
        $kalibrasiData = [
            'positions' => $positions,
            'labels' => $labels,
            'widths' => $widths,
            'refUrl' => self::referenceUrl(),
            'flash' => $flash,
            'flashType' => $flashType,
        ];
        // Admin pusat pakai layout admin, admin seksi pakai layout seksi.
        $layout = !empty($_SESSION['is_admin'])
            ? __DIR__ . '/../../views/layout.php'
            : __DIR__ . '/../../views/layout_seksi.php';
        include $layout;
    }

    /**
     * Simpan posisi semua elemen (fetch JSON dari kanvas).
     * Body: {"items":[{"elemen_key","x_mm","y_mm","max_width_mm"|null,"label"}, ...]}
     */
    public function simpanElemen(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $body = json_decode((string) file_get_contents('php://input'), true);
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

        $count = $this->elemen->saveAll($items, $this->actor());
        echo json_encode(['ok' => true, 'saved' => $count]);
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
     * Upload foto/scan kertas NCR kosong (JPG/PNG, maks 5MB) sebagai
     * background kanvas. Overwrite tiap upload baru.
     */
    public function uploadReferensi(): void
    {
        $this->requireKalibrasiAccess();
        header('Content-Type: application/json; charset=utf-8');

        $f = $_FILES['referensi'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Pilih file JPG/PNG terlebih dahulu.']);
            return;
        }
        if ((int) $f['size'] > 5 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Ukuran maksimal 5MB.']);
            return;
        }
        $info = @getimagesize((string) $f['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Hanya JPG/PNG. Untuk scan PDF, ekspor halaman 1 sebagai JPG lalu unggah ulang.']);
            return;
        }

        if (!is_dir(self::REF_DIR)) {
            mkdir(self::REF_DIR, 0755, true);
        }
        // Hapus referensi lama (semua ekstensi) agar selalu 1 file aktif.
        foreach (glob(self::REF_DIR . '/' . self::REF_BASE . '.*') ?: [] as $old) {
            @unlink($old);
        }
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $dest = self::REF_DIR . '/' . self::REF_BASE . '.' . $ext;
        if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan file di server.']);
            return;
        }

        echo json_encode(['ok' => true, 'url' => self::referenceUrl()]);
    }

    /**
     * Cetak kuitansi satu transaksi (tombol download hijau di kolom Aksi — tidak diubah).
     * ISI teks sama seperti sebelumnya; hanya SUMBER KOORDINAT yang berubah
     * (kalibrasi_kuitansi_elemen per elemen_key). Alur verifikasi tidak disentuh.
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

        $this->pdf->setPositions($this->elemen->getAll());
        $this->pdf->streamKuitansi($trx);
        exit;
    }
}
