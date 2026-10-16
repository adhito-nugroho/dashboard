<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/database_surat_tugas.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\Seksi;
use App\Models\TransferPerjadin;
use App\Services\TransferPerjadinExportService;

/**
 * Modul Transfer Perjalanan Dinas (admin/bendahara only) — berbasis TRANSAKSI.
 *
 * Alur:
 *   /transfer-perjadin          → index()  : daftar transaksi perjalanan dinas +
 *                                            input Transfer Ke & Potongan (massal)
 *   /transfer-perjadin/save     → save()   : simpan konfigurasi (POST)
 *   /transfer-perjadin/export   → export() : unduh Excel (Detail + Rekap pivot)
 *
 * Penerimaan (gross) = transaksi.nilai. Penerimaan bersih = nilai - potongan.
 */
class TransferPerjadinController
{
    private TransferPerjadin $model;
    private Seksi $seksiModel;

    public function __construct(TransferPerjadin $model, Seksi $seksiModel)
    {
        $this->model = $model;
        $this->seksiModel = $seksiModel;
    }

    // ──────────────────────────────────────────────────────────
    // GUARD
    // ──────────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['is_admin'])) {
            $_SESSION['flash_message'] = 'Akses ditolak: fitur Transfer Perjalanan Dinas hanya untuk admin/bendahara.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    // ──────────────────────────────────────────────────────────
    // INDEX
    // ──────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAdmin();

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $seksiId = isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null;
        $q = trim((string) ($_GET['q'] ?? ''));

        $transaksiList = $this->model->getTransaksiPerjadin($tahun, $bulan, $seksiId, $q);
        $configMap = $this->model->getConfigMap();

        // Saran "Transfer Ke" dibatasi ke pegawai peserta Surat Tugas pada transaksi tsb.
        [$stIdByTransaksi, $namaListBySt] = $this->buildTransferKeLists($transaksiList);

        $seksiList = $this->seksiModel->getAll();

        $pageTitle = 'Transfer Perjalanan Dinas';
        $activePage = 'transfer_perjadin';
        $viewFile = __DIR__ . '/../../views/transfer_perjadin/index.php';
        include __DIR__ . '/../../views/layout.php';
    }

    // ──────────────────────────────────────────────────────────
    // SAVE
    // ──────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->requireAdmin();

        $transferKeInput = $_POST['transfer_ke'] ?? [];
        $potonganInput = $_POST['potongan'] ?? [];
        if (!is_array($transferKeInput)) {
            $transferKeInput = [];
        }
        if (!is_array($potonganInput)) {
            $potonganInput = [];
        }

        $ids = array_unique(array_merge(array_keys($transferKeInput), array_keys($potonganInput)));
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $rows = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $rows[$id] = [
                'transfer_ke' => trim((string) ($transferKeInput[$id] ?? '')),
                'potongan' => $this->parseRupiah($potonganInput[$id] ?? 0),
            ];
        }

        // Resolusi Surat Tugas tiap transaksi, lalu validasi & propagasi per ST.
        [$stByTrx, $participants] = $this->resolveStContext($rows);
        $diabaikan = $this->filterInvalidTransferKe($rows, $stByTrx, $participants);
        $propagated = $this->propagateWithinSt($rows, $stByTrx);

        try {
            $saved = $this->model->saveBulk($rows, $userId);
            $message = 'Konfigurasi transfer tersimpan (' . $saved . ' transaksi dikonfigurasi).';
            if ($propagated > 0) {
                $message .= ' ' . $propagated . ' baris lain pada Surat Tugas yang sama otomatis disamakan.';
            }
            if ($diabaikan > 0) {
                $message .= ' ' . $diabaikan . ' nama Transfer Ke diabaikan karena bukan pegawai pada Surat Tugas transaksi tersebut.';
            }
            $this->redirectWithMessage(
                base_url('transfer-perjadin'),
                $diabaikan > 0 ? 'info' : 'success',
                $message
            );
        } catch (\Throwable $e) {
            error_log('TransferPerjadinController::save error: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Gagal menyimpan konfigurasi transfer.');
        }
    }

    // ──────────────────────────────────────────────────────────
    // EXPORT
    // ──────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAdmin();

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : null;

        $configured = $this->model->getConfiguredTransaksi();
        $rows = [];
        foreach ($configured as $c) {
            if ($tahun !== null) {
                $mulai = (string) ($c['tanggal'] ?? '');
                if ((int) date('Y', strtotime($mulai)) !== $tahun) {
                    continue;
                }
            }
            $rows[] = [
                'uraian' => (string) ($c['uraian'] ?? ''),
                'nama' => (string) ($c['nama_penerima'] ?? ''),
                'nilai' => (float) ($c['nilai'] ?? 0),
                'transfer_ke' => (string) ($c['transfer_ke'] ?? ''),
                'potongan' => (float) ($c['potongan'] ?? 0),
            ];
        }

        if ($rows === []) {
            $this->redirectWithMessage(base_url('transfer-perjadin'), 'error', 'Belum ada transaksi yang dikonfigurasi transfernya.');
            return;
        }

        $suffix = $tahun !== null ? '_' . $tahun : '';
        (new TransferPerjadinExportService())->download($rows, 'Transfer_Perjalanan_Dinas' . $suffix . '_' . date('Ymd_His') . '.xlsx');
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────

    private function parseRupiah($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        $s = preg_replace('/[^0-9,.\-]/', '', $s) ?? '';
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.')) {
            if (preg_match('/\.\d{3}(\D|$)/', $s)) {
                $s = str_replace('.', '', $s);
            }
        }
        return max(0.0, (float) $s);
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

    /**
     * Bangun peta id-transaksi => id Surat Tugas dan id-ST => daftar nama peserta.
     *
     * @return array{0: array<int,int>, 1: array<int, string[]>}
     */
    private function buildTransferKeLists(array $transaksiList): array
    {
        $stIds = [];
        $nomors = [];
        foreach ($transaksiList as $t) {
            $rid = (int) ($t['surat_tugas_ref_id'] ?? 0);
            if ($rid > 0) {
                $stIds[$rid] = true;
                continue;
            }
            $n = trim((string) ($t['nomor_surat_tugas'] ?? ''));
            if ($n !== '') {
                $nomors[$n] = true;
            }
        }

        $nomorToSt = $this->model->resolveSuratTugasIdsByNomor(array_keys($nomors));

        $stIdByTransaksi = [];
        foreach ($transaksiList as $t) {
            $tid = (int) $t['id'];
            $rid = (int) ($t['surat_tugas_ref_id'] ?? 0);
            if ($rid <= 0) {
                $n = trim((string) ($t['nomor_surat_tugas'] ?? ''));
                $rid = (int) ($nomorToSt[$n] ?? 0);
            }
            $stIdByTransaksi[$tid] = $rid;
            if ($rid > 0) {
                $stIds[$rid] = true;
            }
        }

        $participants = $this->model->getPegawaiNamesBySuratTugas(array_keys($stIds));

        $sets = [];
        foreach ($participants as $st => $names) {
            foreach ($names as $n) {
                $sets[(int) $st][$n] = true;
            }
        }
        // Pastikan penerima transaksi sendiri tetap muncul (mis. ST belum punya peserta).
        foreach ($transaksiList as $t) {
            $tid = (int) $t['id'];
            $st = $stIdByTransaksi[$tid] ?? 0;
            $nama = trim((string) ($t['nama_penerima'] ?? ''));
            if ($nama !== '') {
                $sets[$st][$nama] = true;
            }
        }

        $namaListBySt = [];
        foreach ($sets as $st => $set) {
            $list = array_keys($set);
            sort($list, SORT_NATURAL | SORT_FLAG_CASE);
            $namaListBySt[(int) $st] = $list;
        }

        return [$stIdByTransaksi, $namaListBySt];
    }

    /**
     * Resolusi Surat Tugas per transaksi + daftar peserta per ST.
     *
     * @param array<int, array{transfer_ke:string, potongan:float}> $rows
     * @return array{0: array<int,int>, 1: array<int, string[]>}
     */
    private function resolveStContext(array $rows): array
    {
        $refs = $this->model->getTransaksiStRefs(array_keys($rows));

        $nomors = [];
        foreach ($refs as $r) {
            if ($r['ref'] <= 0 && $r['nomor'] !== '') {
                $nomors[$r['nomor']] = true;
            }
        }
        $nomorToSt = $this->model->resolveSuratTugasIdsByNomor(array_keys($nomors));

        $stByTrx = [];
        $stIds = [];
        foreach ($refs as $id => $r) {
            $st = $r['ref'] > 0 ? $r['ref'] : (int) ($nomorToSt[$r['nomor']] ?? 0);
            $stByTrx[(int) $id] = $st;
            if ($st > 0) {
                $stIds[$st] = true;
            }
        }

        $participants = $this->model->getPegawaiNamesBySuratTugas(array_keys($stIds));

        return [$stByTrx, $participants];
    }

    /**
     * Kosongkan transfer_ke yang bukan pegawai pada ST transaksi bersangkutan.
     * Bila ST tidak punya data peserta, nilai dibiarkan (tidak dapat divalidasi).
     *
     * @param array<int, array{transfer_ke:string, potongan:float}> $rows
     * @param array<int,int> $stByTrx
     * @param array<int, string[]> $participants
     * @return int jumlah yang diabaikan
     */
    private function filterInvalidTransferKe(array &$rows, array $stByTrx, array $participants): int
    {
        $cleared = 0;
        foreach ($rows as $id => $cfg) {
            $ke = trim((string) $cfg['transfer_ke']);
            if ($ke === '') {
                continue;
            }
            $names = $participants[$stByTrx[(int) $id] ?? 0] ?? [];
            if ($names === []) {
                continue;
            }
            $allowed = [];
            foreach ($names as $n) {
                $allowed[$this->normNama($n)] = true;
            }
            if (!isset($allowed[$this->normNama($ke)])) {
                $rows[$id]['transfer_ke'] = '';
                $cleared++;
            }
        }
        return $cleared;
    }

    /**
     * Dalam satu Surat Tugas, Transfer Ke & Potongan cukup diisi sekali:
     * nilai yang terisi disebarkan ke baris lain (yang masih kosong) pada ST yang sama.
     *
     * @param array<int, array{transfer_ke:string, potongan:float}> $rows
     * @param array<int,int> $stByTrx
     * @return int jumlah baris yang diisi otomatis
     */
    private function propagateWithinSt(array &$rows, array $stByTrx): int
    {
        $groups = [];
        foreach ($rows as $id => $cfg) {
            $st = $stByTrx[(int) $id] ?? 0;
            if ($st > 0) {
                $groups[$st][] = (int) $id;
            }
        }

        $filled = 0;
        foreach ($groups as $ids) {
            $sourceKe = '';
            $sourcePot = 0.0;
            foreach ($ids as $id) {
                if ($sourceKe === '' && trim((string) $rows[$id]['transfer_ke']) !== '') {
                    $sourceKe = trim((string) $rows[$id]['transfer_ke']);
                }
                if ($sourcePot <= 0 && (float) $rows[$id]['potongan'] > 0) {
                    $sourcePot = (float) $rows[$id]['potongan'];
                }
            }
            if ($sourceKe === '' && $sourcePot <= 0) {
                continue;
            }
            foreach ($ids as $id) {
                if (trim((string) $rows[$id]['transfer_ke']) === '' && $sourceKe !== '') {
                    $rows[$id]['transfer_ke'] = $sourceKe;
                    $filled++;
                }
                if ((float) $rows[$id]['potongan'] <= 0 && $sourcePot > 0) {
                    $rows[$id]['potongan'] = $sourcePot;
                    $filled++;
                }
            }
        }
        return $filled;
    }

    private function normNama(string $s): string
    {
        $s = strtoupper(trim($s));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s*,\s*/', ',', $s) ?? $s;
        $s = str_replace('.', '', $s);
        return trim($s, " ,");
    }
}
