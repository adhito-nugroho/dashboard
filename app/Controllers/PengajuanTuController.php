<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\PengajuanTu;
use App\Models\KasBank;
use App\Services\PengajuanTuExportService;
use App\Services\TuStsExportService;

/**
 * Controller modul Tambah Uang (TU) — dikelola penuh oleh admin/bendahara.
 *
 * Routes:
 *   GET  /pengajuan-tu                   → index()            : daftar semua TU
 *   GET  /pengajuan-tu/export            → export()           : unduh Excel lengkap
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
        $totals = $this->model->getTotals($status ?: null, $seksiId, $tahun, $bulan);

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

    // ── EXPORT ────────────────────────────────────────────────────────────

    public function export(): void
    {
        $this->requireAdmin();

        $status  = trim($_GET['status'] ?? '');
        $tahun   = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $bulan   = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int) $_GET['bulan'] : null;
        $seksiId = isset($_GET['seksi_id']) && $_GET['seksi_id'] !== '' ? (int) $_GET['seksi_id'] : null;
        $q       = trim($_GET['q'] ?? '');

        $allowedStatus = ['', 'diajukan', 'disetujui', 'cair', 'selesai', 'ditolak'];
        if (!in_array($status, $allowedStatus, true)) $status = '';

        try {
            $report = $this->buildExportReport($status, $tahun, $bulan, $seksiId, $q);
            (new PengajuanTuExportService())->download($report);
        } catch (\Throwable $e) {
            error_log('Export pengajuan TU gagal: ' . $e->getMessage());
            if (!headers_sent()) {
                $this->flash('error', 'Export Excel gagal dibuat. Silakan coba lagi.');
                $this->redirect(base_url('pengajuan-tu?tahun=' . $tahun));
            }
        }
    }

    /**
     * Rekap nilai setor (STS) dana TU per sub kegiatan, untuk TU berstatus
     * cair maupun selesai — rekap tetap tersedia setelah siklus TU ditutup.
     */
    public function exportSts(): void
    {
        $this->requireAdmin();

        try {
            $data = $this->model->getRekapSisaPerSubKegiatan();
            if ($data['rows'] === []) {
                $this->flash('info', 'Belum ada TU berstatus cair atau selesai, jadi belum ada dana yang perlu disetor.');
                $this->redirect(base_url('pengajuan-tu'));
            }
            (new TuStsExportService())->download($data);
        } catch (\Throwable $e) {
            error_log('Export rekap STS TU gagal: ' . $e->getMessage());
            if (!headers_sent()) {
                $this->flash('error', 'Rekap STS gagal dibuat. Silakan coba lagi.');
                $this->redirect(base_url('pengajuan-tu'));
            }
        }
    }

    /**
     * Data lengkap export: rekap pengajuan, rincian rekening, dan riwayat
     * pembayaran untuk filter yang sama dengan halaman daftar.
     */
    private function buildExportReport(
        string $status,
        int $tahun,
        ?int $bulan,
        ?int $seksiId,
        string $q
    ): array {
        $items = $this->model->getAll($status ?: null, $seksiId, $tahun, $bulan, null, 0);

        if ($q !== '') {
            $items = array_values(array_filter(
                $items,
                fn(array $row) => stripos((string) $row['nomor_pengajuan'], $q) !== false
            ));
        }

        $tuIds      = array_map(fn(array $row) => (int) $row['id'], $items);
        $rekenings  = $this->model->getRekeningsByTuIds($tuIds);
        $transaksis = $this->model->getTransaksiByTuIds($tuIds);

        $statusOrder = ['diajukan', 'disetujui', 'cair', 'selesai', 'ditolak'];
        $groups = [];
        foreach ($statusOrder as $key) {
            $groups[$key] = [
                'count' => 0, 'kebutuhan' => 0.0, 'cair' => 0.0,
                'terpakai' => 0.0, 'sisa' => 0.0, 'disetor' => 0.0, 'jatuh_tempo' => 0,
            ];
        }

        $totals = [
            'count' => 0, 'kebutuhan' => 0.0, 'cair' => 0.0, 'terpakai' => 0.0,
            'disetor' => 0.0, 'sisa' => 0.0, 'jatuh_tempo' => 0, 'pct_serapan' => 0.0,
        ];

        $today = date('Y-m-d');
        $rows  = [];
        $meta  = [];

        foreach ($items as $item) {
            $statusRow  = (string) $item['status'];
            $kebutuhan  = (float) $item['kebutuhan'];
            $cair       = (float) ($item['nominal_cair'] ?? 0);
            $terpakai   = (float) ($item['nominal_terpakai'] ?? 0);
            if ($statusRow === 'selesai') {
                // nominal_digunakan adalah snapshot saat TU ditutup; pakai bila
                // transaksi sumbernya sudah tidak ada lagi.
                $terpakai = max($terpakai, (float) ($item['nominal_digunakan'] ?? 0));
            }
            $disetor    = (float) ($item['nominal_disetor'] ?? 0);
            $sisa       = max(0.0, $cair - $terpakai - $disetor);
            $jatuhTempo = $statusRow === 'cair'
                && !empty($item['batas_spj'])
                && $item['batas_spj'] < $today;

            $seksi   = trim(($item['kode_seksi'] ?? '') . ' — ' . ($item['nama_seksi'] ?? ''), ' —');
            $periode = $this->namaBulan((int) $item['bulan']) . ' ' . (int) $item['tahun'];

            $ket = [];
            switch ($statusRow) {
                case 'diajukan':
                    $ket[] = 'Menunggu pencairan SP2D';
                    break;
                case 'disetujui':
                    $ket[] = 'Disetujui, belum dicairkan';
                    break;
                case 'cair':
                    $ket[] = 'SP2D sudah cair';
                    $ket[] = $sisa > 0.005
                        ? 'Sisa dana Rp ' . number_format($sisa, 0, ',', '.')
                        : 'Dana habis terpakai';
                    break;
                case 'selesai':
                    $ket[] = 'Siklus selesai';
                    if ($disetor > 0.005) {
                        $ket[] = 'Disetor Rp ' . number_format($disetor, 0, ',', '.');
                    }
                    break;
                case 'ditolak':
                    $ket[] = 'Ditolak';
                    break;
            }
            if ($jatuhTempo) {
                $ket[] = 'Jatuh tempo SPJ';
            }

            $rows[] = [
                'nomor_pengajuan'   => $item['nomor_pengajuan'],
                'tanggal_pengajuan' => $item['tanggal_pengajuan'],
                'seksi'             => $seksi,
                'periode'           => $periode,
                'keperluan'         => $item['keperluan'],
                'jumlah_rekening'   => (int) ($item['jumlah_rekening'] ?? 0),
                'kebutuhan'         => $kebutuhan,
                'status'            => $statusRow,
                'nomor_sp2d'        => $item['nomor_sp2d'] ?? '',
                'tanggal_cair'      => $item['tanggal_cair'] ?? null,
                'nominal_cair'      => $cair,
                'nominal_terpakai'  => $terpakai,
                'nominal_disetor'   => $disetor,
                'sisa'              => $sisa,
                'batas_spj'         => $item['batas_spj'] ?? null,
                'jatuh_tempo'       => $jatuhTempo,
                'created_by'        => $item['created_by_name'] ?? '',
                'keterangan'        => implode('; ', $ket),
            ];

            $meta[(int) $item['id']] = [
                'nomor_pengajuan'   => $item['nomor_pengajuan'],
                'tanggal_pengajuan' => $item['tanggal_pengajuan'],
                'seksi'             => $seksi,
                'periode'           => $periode,
                'status'            => $statusRow,
            ];

            $totals['count']++;
            $totals['kebutuhan'] += $kebutuhan;
            $totals['cair']      += $cair;
            $totals['terpakai']  += $terpakai;
            $totals['disetor']   += $disetor;
            $totals['sisa']      += $sisa;
            if ($jatuhTempo) $totals['jatuh_tempo']++;

            if (isset($groups[$statusRow])) {
                $groups[$statusRow]['count']++;
                $groups[$statusRow]['kebutuhan'] += $kebutuhan;
                $groups[$statusRow]['cair']      += $cair;
                $groups[$statusRow]['terpakai']  += $terpakai;
                $groups[$statusRow]['disetor']   += $disetor;
                $groups[$statusRow]['sisa']      += $sisa;
                if ($jatuhTempo) $groups[$statusRow]['jatuh_tempo']++;
            }
        }

        $totals['pct_serapan'] = $totals['cair'] > 0 ? $totals['terpakai'] / $totals['cair'] : 0.0;

        $detailRekenings = [];
        foreach ($rekenings as $rek) {
            $tuId = (int) $rek['pengajuan_tu_id'];
            if (!isset($meta[$tuId])) continue;
            $m = $meta[$tuId];

            $detailRekenings[] = [
                'nomor_pengajuan'   => $m['nomor_pengajuan'],
                'tanggal_pengajuan' => $m['tanggal_pengajuan'],
                'seksi'             => $m['seksi'],
                'periode'           => $m['periode'],
                'status'            => $m['status'],
                'kode_rekening'     => $rek['kode_rekening'],
                'nama_rekening'     => $rek['nama_rekening'],
                'kode_sub_kegiatan' => $rek['kode_sub_kegiatan'],
                'nama_sub_kegiatan' => $rek['nama_sub_kegiatan'],
                'nominal'           => (float) $rek['nominal'],
            ];
        }

        $detailTransaksi = [];
        foreach ($transaksis as $trx) {
            $tuId = (int) $trx['pengajuan_tu_id'];
            if (!isset($meta[$tuId])) continue;

            $detailTransaksi[] = [
                'nomor_pengajuan'   => $meta[$tuId]['nomor_pengajuan'],
                'tanggal'           => $trx['tanggal_lunas_dibayar'] ?: $trx['tanggal'],
                'kode_rekening'     => $trx['kode_rekening'],
                'nama_rekening'     => $trx['nama_rekening'],
                'kode_sub_kegiatan' => $trx['kode_sub_kegiatan'],
                'nama_sub_kegiatan' => $trx['nama_sub_kegiatan'],
                'uraian'            => $trx['uraian'],
                'nama_penerima'     => $trx['nama_penerima'],
                'jenis_transaksi'   => $trx['jenis_transaksi'],
                'status'            => $trx['status'],
                'nomor_bukti'       => $trx['nomor_bukti'],
                'nilai'             => (float) $trx['nilai'],
            ];
        }

        $filterParts = ['Tahun ' . $tahun];
        if ($bulan) {
            $filterParts[] = 'Bulan ' . $this->namaBulan($bulan);
        }
        if ($status !== '') {
            $labelStatus = [
                'diajukan' => 'Menunggu Cair', 'disetujui' => 'Disetujui (lama)',
                'cair' => 'SP2D Cair', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak',
            ];
            $filterParts[] = 'Status: ' . ($labelStatus[$status] ?? $status);
        }
        if ($seksiId) {
            $stmt = \Database::getConnection()->prepare('SELECT nama_seksi FROM seksi WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $seksiId]);
            $namaSeksi = $stmt->fetchColumn();
            $filterParts[] = 'Seksi: ' . ($namaSeksi !== false && $namaSeksi !== null ? $namaSeksi : 'ID ' . $seksiId);
        }
        if ($q !== '') {
            $filterParts[] = 'Pencarian nomor: "' . $q . '"';
        }
        $filterText = implode(' • ', $filterParts) . ' • ' . count($rows) . ' pengajuan';

        return [
            'tahun'        => $tahun,
            'bulan'        => $bulan,
            'filterText'   => $filterText,
            'generatedAt'  => date('d-m-Y H:i'),
            'rows'         => $rows,
            'rekenings'    => $detailRekenings,
            'transaksis'   => $detailTransaksi,
            'totals'       => $totals,
            'statusGroups' => $groups,
        ];
    }

    private function namaBulan(int $bulan): string
    {
        $map = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $map[$bulan] ?? (string) $bulan;
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
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
            'program_id'        => $_POST['program_id']      ?? '',
            'kegiatan_id'       => $_POST['kegiatan_id']     ?? '',
            'sub_kegiatan_id'   => !empty($_POST['sub_kegiatan_id']) ? (int) $_POST['sub_kegiatan_id'] : null,
            'rekenings'         => $this->parseRekenings($_POST['rekenings'] ?? []),
        ];

        $errors    = $this->validateBatch($form);
        $kebutuhan = array_sum(array_column($form['rekenings'], 'nominal'));
        $seksiId   = $form['sub_kegiatan_id'] ? $this->seksiIdFromSubKegiatan((int) $form['sub_kegiatan_id']) : null;

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
                $form['rekenings']
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
            'keperluan'         => trim($_POST['keperluan'] ?? ''),
            'program_id'        => $_POST['program_id']      ?? '',
            'kegiatan_id'       => $_POST['kegiatan_id']     ?? '',
            'sub_kegiatan_id'   => !empty($_POST['sub_kegiatan_id']) ? (int) $_POST['sub_kegiatan_id'] : null,
            'rekenings'         => $this->parseRekenings($_POST['rekenings'] ?? []),
        ];

        $errors    = $this->validateBatch($form, $tu);
        $kebutuhan = array_sum(array_column($form['rekenings'], 'nominal'));

        if (!empty($errors)) {
            $form     = array_merge($tu, $form);
            $programs = $this->getPrograms();
            $pageTitle  = 'Edit Pengajuan TU';
            $activePage = 'pengajuan_tu';
            $viewFile   = __DIR__ . '/../../views/pengajuan_tu/form.php';
            include __DIR__ . '/../../views/layout.php';
            return;
        }

        try {
            $ok = $this->model->update($id, $kebutuhan, $form['keperluan'], $form['tanggal_pengajuan'], $form['rekenings']);
        } catch (\Throwable $e) {
            error_log('PengajuanTuController::update error: ' . $e->getMessage());
            $this->flash('error', 'Gagal memperbarui: ' . $e->getMessage());
            $this->redirect(base_url('pengajuan-tu/edit/' . $id));
        }
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

    /**
     * Bersihkan input rekenings[] menjadi daftar {rekening_id, nominal}.
     * Baris tanpa rekening atau nominal 0 diabaikan.
     *
     * @return array<int, array{rekening_id:int, nominal:float}>
     */
    private function parseRekenings(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rekeningId = (int) ($row['rekening_id'] ?? 0);
            $nominal    = $this->parseNominal($row['nominal'] ?? '0');
            if ($rekeningId <= 0 || $nominal <= 0) {
                continue;
            }
            $out[] = ['rekening_id' => $rekeningId, 'nominal' => $nominal];
        }
        return $out;
    }

    /**
     * Validasi input batch: sub kegiatan wajib, minimal satu rekening,
     * semua rekening milik sub kegiatan tersebut, dan tidak duplikat.
     *
     * @param array<string,mixed>      $form
     * @param array<string,mixed>|null $tu   data TU saat edit (untuk cek seksi)
     * @return string[]
     */
    private function validateBatch(array $form, ?array $tu = null): array
    {
        $errors = [];

        if (empty($form['tanggal_pengajuan']) || !strtotime((string) $form['tanggal_pengajuan'])) {
            $errors[] = 'Tanggal pengajuan tidak valid.';
        }
        if (isset($form['bulan']) && ($form['bulan'] < 1 || $form['bulan'] > 12)) {
            $errors[] = 'Bulan tidak valid.';
        }
        if (isset($form['tahun']) && ($form['tahun'] < 2020 || $form['tahun'] > 2099)) {
            $errors[] = 'Tahun tidak valid.';
        }
        if (trim((string) ($form['keperluan'] ?? '')) === '') {
            $errors[] = 'Keperluan / uraian kegiatan wajib diisi.';
        }
        if (empty($form['sub_kegiatan_id'])) {
            $errors[] = 'Sub kegiatan wajib dipilih.';
        }

        $rows = $form['rekenings'] ?? [];
        if (empty($rows)) {
            $errors[] = 'Minimal satu rekening harus diisi nominalnya.';
            return $errors;
        }

        $seen = [];
        foreach ($rows as $row) {
            $rid = (int) ($row['rekening_id'] ?? 0);
            if (isset($seen[$rid])) {
                $errors[] = 'Rekening yang sama dipilih lebih dari sekali.';
            }
            $seen[$rid] = true;
        }

        if (!empty($form['sub_kegiatan_id'])) {
            $subKegiatanId = (int) $form['sub_kegiatan_id'];
            $seksiId = $this->seksiIdFromSubKegiatan($subKegiatanId);

            if ($seksiId === null) {
                $errors[] = 'Sub kegiatan tidak memiliki seksi yang valid.';
            } elseif ($tu !== null && $seksiId !== (int) $tu['seksi_id']) {
                $errors[] = 'Sub kegiatan harus berasal dari seksi yang sama dengan pengajuan TU ini.';
            }

            $ids = array_values(array_unique(array_map(fn($r) => (int) $r['rekening_id'], $rows)));
            if (!$this->rekeningsBelongToSubKegiatan($ids, $subKegiatanId)) {
                $errors[] = 'Semua rekening harus berasal dari sub kegiatan yang dipilih.';
            }
        }

        return $errors;
    }

    private function seksiIdFromSubKegiatan(int $subKegiatanId): ?int
    {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT seksi_id FROM sub_kegiatan WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $subKegiatanId]);
        $seksiId = $stmt->fetchColumn();
        if ($seksiId === false || $seksiId === null || (int) $seksiId === 0) {
            return null;
        }
        return (int) $seksiId;
    }

    /**
     * @param int[] $rekeningIds
     */
    private function rekeningsBelongToSubKegiatan(array $rekeningIds, int $subKegiatanId): bool
    {
        if (empty($rekeningIds)) {
            return true;
        }
        $db = \Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($rekeningIds), '?'));
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM rekening
            WHERE id IN ({$placeholders}) AND sub_kegiatan_id = ?
        ");
        $stmt->execute([...$rekeningIds, $subKegiatanId]);
        return (int) $stmt->fetchColumn() === count($rekeningIds);
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
