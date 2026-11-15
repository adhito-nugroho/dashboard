<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

use App\Models\RencanaPenyerapan;
use App\Models\Rekening;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rak;
use App\Services\RencanaPenyerapanExportService;

/**
 * Perencanaan timeline penyerapan anggaran per kode rekening.
 *
 * Routes:
 *   GET  /rencana-penyerapan                 → index()       : timeline + form entri inline
 *   GET  /rencana-penyerapan/export          → export()      : rekap Excel (ringkasan + rekap + detail)
 *   GET  /rencana-penyerapan/create          → create()      : buka form inline untuk rekening
 *   POST /rencana-penyerapan/store           → store()
 *   GET  /rencana-penyerapan/edit/{id}       → edit()        : buka form inline untuk rencana
 *   POST /rencana-penyerapan/update/{id}     → update()
 *   POST /rencana-penyerapan/delete/{id}     → delete()
 *   GET  /rencana-penyerapan/rekening-info   → rekeningInfo(): JSON pagu/rencana/realisasi
 */
class RencanaPenyerapanController
{
    private const PER_PAGE = 8;

    private RencanaPenyerapan $model;
    private Rekening $rekeningModel;
    private Kegiatan $kegiatanModel;
    private SubKegiatan $subKegiatanModel;
    private Rak $rakModel;

    public function __construct(
        RencanaPenyerapan $model,
        Rekening $rekeningModel,
        Kegiatan $kegiatanModel,
        SubKegiatan $subKegiatanModel,
        Rak $rakModel
    ) {
        $this->model = $model;
        $this->rekeningModel = $rekeningModel;
        $this->kegiatanModel = $kegiatanModel;
        $this->subKegiatanModel = $subKegiatanModel;
        $this->rakModel = $rakModel;
    }

    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    /**
     * @return array{0:int,1:?int,2:?int,3:?string,4:string,5:string}
     */
    private function filtersFromQuery(): array
    {
        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $kegiatanId = isset($_GET['kegiatan_id']) && $_GET['kegiatan_id'] !== '' ? (int) $_GET['kegiatan_id'] : null;
        $subKegiatanId = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;
        $mekanisme = strtoupper(trim((string) ($_GET['mekanisme'] ?? '')));
        if (!in_array($mekanisme, ['GU', 'TU', 'LS'], true)) {
            $mekanisme = null;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['selesai', 'sudah', 'belum'], true)) {
            $status = '';
        }

        return [$tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q, $status];
    }

    public function index(): void
    {
        $this->requireAdmin();

        $inlineForm = $_SESSION['rp_inline']['form'] ?? null;
        $inlineErrors = $_SESSION['rp_inline']['errors'] ?? [];
        $inlineAction = $_SESSION['rp_inline']['action'] ?? null;
        unset($_SESSION['rp_inline']);

        [$tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q, $status] = $this->filtersFromQuery();

        $data = $this->loadRencanaData($tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q);

        $groups = $data['groups'];
        $totalRencana = $data['totalRencana'];
        $totalTuPlan = $data['totalTuPlan'];
        $totalPagu = $data['totalPagu'];
        $totalRealisasi = $data['totalRealisasi'];
        $totalSisaKapasitas = $data['totalSisaKapasitas'];
        $selesaiList = $data['selesaiList'];
        $belumDirencanakan = $data['belumDirencanakan'];
        $belumCount = $data['belumCount'];
        $belumDirencanakan = array_slice($belumDirencanakan, 0, 100);

        if ($status === 'selesai') {
            $groups = array_values(array_filter($groups, fn($g) => !empty($g['selesai'])));
        } elseif ($status === 'sudah') {
            $groups = array_values(array_filter($groups, fn($g) => empty($g['selesai'])));
        } elseif ($status === 'belum') {
            $groups = [];
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $focusRekeningId = (int) ($inlineForm['rekening_id'] ?? 0);
        if ($focusRekeningId > 0) {
            foreach ($groups as $idx => $group) {
                if ((int) $group['rekening_id'] === $focusRekeningId) {
                    $page = intdiv($idx, self::PER_PAGE) + 1;
                    break;
                }
            }
        }
        $totalGroups = count($groups);
        $totalPages = max(1, (int) ceil($totalGroups / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $groups = array_slice($groups, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $pagination = [
            'page'       => $page,
            'perPage'    => self::PER_PAGE,
            'total'      => $totalGroups,
            'totalPages' => $totalPages,
            'baseUrl'    => base_url('rencana-penyerapan'),
        ];

        $queryParams = array_filter([
            'tahun'           => $tahun,
            'kegiatan_id'     => $kegiatanId,
            'sub_kegiatan_id' => $subKegiatanId,
            'mekanisme'       => $mekanisme,
            'status'          => $status,
            'q'               => $q,
        ], fn($v) => $v !== null && $v !== '');

        $filterKegiatanList = $this->kegiatanModel->getAll();
        $filterSubKegiatanList = $this->subKegiatanModel->getAll();
        $filterKegiatan = $kegiatanId;
        $filterSubKegiatan = $subKegiatanId;
        $filterMekanisme = $mekanisme;
        $filterStatus = $status;
        $filterQ = $q;

        $pageTitle = 'Rencana Penyerapan';
        $activePage = 'rencana_penyerapan';
        $viewFile = __DIR__ . '/../../views/rencana_penyerapan/index.php';

        include __DIR__ . '/../../views/layout.php';
    }

    /**
     * Sumber data tunggal untuk halaman timeline dan export Excel.
     */
    private function loadRencanaData(
        int $tahun,
        ?int $kegiatanId,
        ?int $subKegiatanId,
        ?string $mekanisme,
        string $q
    ): array {
        $plans = $this->model->getAllWithFilters($tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q ?: null);
        $paguMap = $this->model->getPaguMap($tahun, $kegiatanId, $subKegiatanId);
        $realisasiMap = $this->model->getRealisasiMap($tahun, $kegiatanId, $subKegiatanId);
        $realisasiBulananMap = $this->model->getRealisasiMonthlyMap($tahun, $kegiatanId, $subKegiatanId);
        $tuBulananMap = $this->model->getTuPlanMonthlyMap($tahun, $kegiatanId, $subKegiatanId);
        $selesaiMap = $this->model->getSelesaiMap($tahun);
        $pickerRekening = $this->model->getRekeningPickerList($tahun, $kegiatanId, $subKegiatanId);

        $tuTotalMap = [];
        foreach ($tuBulananMap as $rekeningId => $bulanan) {
            $tuTotalMap[$rekeningId] = array_sum($bulanan);
        }

        $groups = [];
        foreach ($plans as $plan) {
            $rekeningId = (int) $plan['rekening_id'];
            if (!isset($groups[$rekeningId])) {
                $groups[$rekeningId] = [
                    'rekening_id'       => $rekeningId,
                    'kode_rekening'     => $plan['kode_rekening'],
                    'nama_rekening'     => $plan['nama_rekening'],
                    'kode_program'      => $plan['kode_program'],
                    'nama_program'      => $plan['nama_program'],
                    'kode_kegiatan'     => $plan['kode_kegiatan'],
                    'nama_kegiatan'     => $plan['nama_kegiatan'],
                    'kode_sub_kegiatan' => $plan['kode_sub_kegiatan'],
                    'nama_sub_kegiatan' => $plan['nama_sub_kegiatan'],
                    'nama_seksi'        => $plan['nama_seksi'] ?? null,
                    'tahun'             => (int) $plan['tahun'],
                    'pagu'              => $paguMap[$rekeningId] ?? 0,
                    'realisasi'         => $realisasiMap[$rekeningId] ?? ['total' => 0, 'GU' => 0, 'TU' => 0, 'LS' => 0, 'UP' => 0],
                    'realisasi_bulanan' => $realisasiBulananMap[$rekeningId] ?? [],
                    'plans'             => [],
                    'total_rencana'     => 0,
                ];
            }
            $groups[$rekeningId]['plans'][] = $plan;
            $groups[$rekeningId]['total_rencana'] += (float) $plan['nilai_rencana'];
        }

        $pickerIndex = [];
        foreach ($pickerRekening as $rek) {
            $pickerIndex[(int) $rek['id']] = $rek;
        }

        foreach ($tuTotalMap as $rekeningId => $totalTu) {
            if ($totalTu <= 0 || isset($groups[$rekeningId])) {
                continue;
            }
            $rek = $pickerIndex[(int) $rekeningId] ?? null;
            if (!$rek || (float) $rek['total_pagu'] <= 0) {
                continue;
            }
            $groups[(int) $rekeningId] = [
                'rekening_id'       => (int) $rekeningId,
                'kode_rekening'     => $rek['kode_rekening'],
                'nama_rekening'     => $rek['nama_rekening'],
                'kode_program'      => $rek['kode_program'],
                'nama_program'      => $rek['nama_program'],
                'kode_kegiatan'     => $rek['kode_kegiatan'],
                'nama_kegiatan'     => $rek['nama_kegiatan'],
                'kode_sub_kegiatan' => $rek['kode_sub_kegiatan'],
                'nama_sub_kegiatan' => $rek['nama_sub_kegiatan'],
                'nama_seksi'        => $rek['nama_seksi'] ?? null,
                'tahun'             => $tahun,
                'pagu'              => (float) $rek['total_pagu'],
                'realisasi'         => $realisasiMap[(int) $rekeningId] ?? ['total' => 0, 'GU' => 0, 'TU' => 0, 'LS' => 0, 'UP' => 0],
                'realisasi_bulanan' => $realisasiBulananMap[(int) $rekeningId] ?? [],
                'plans'             => [],
                'total_rencana'     => 0,
            ];
        }

        foreach ($groups as &$group) {
            $rekeningId = (int) $group['rekening_id'];
            $group['tu_plan'] = (float) ($tuTotalMap[$rekeningId] ?? 0);
            $group['tu_bulanan'] = $tuBulananMap[$rekeningId] ?? [];
            $group['sisa_kapasitas'] = (float) $group['pagu']
                - (float) $group['realisasi']['total']
                - (float) $group['total_rencana']
                - $group['tu_plan'];
            $group['selesai_manual'] = isset($selesaiMap[$rekeningId]);
            $group['selesai_otomatis'] = !$group['selesai_manual']
                && (float) $group['pagu'] > 0
                && $group['sisa_kapasitas'] <= 0.005;
            $group['selesai'] = $group['selesai_manual'] || $group['selesai_otomatis'];
        }
        unset($group);

        $groups = array_values($groups);

        $totalRencana = 0.0;
        foreach ($groups as $group) {
            $totalRencana += $group['total_rencana'];
        }
        $totalTuPlan = array_sum($tuTotalMap);
        $totalPagu = array_sum($paguMap);
        $totalRealisasi = 0.0;
        foreach ($realisasiMap as $row) {
            $totalRealisasi += (float) $row['total'];
        }
        $totalSisaKapasitas = $totalPagu - $totalRealisasi - $totalRencana - $totalTuPlan;

        $selesaiList = [];
        foreach ($pickerRekening as $rek) {
            if (empty($rek['selesai_id'])) {
                continue;
            }
            $rekeningId = (int) $rek['id'];
            $paguRek = (float) $rek['total_pagu'];
            $realRek = (float) $rek['total_realisasi'];
            $rencanaRek = (float) $rek['total_rencana'];
            $tuRek = (float) $rek['total_tu'];
            $rek['sisa_kapasitas'] = $paguRek - $realRek - $rencanaRek - $tuRek;
            $rek['keterangan_selesai'] = $selesaiMap[$rekeningId]['keterangan'] ?? null;
            $selesaiList[] = $rek;
        }

        $belumDirencanakan = $this->model->getRekeningBelumDirencanakan($tahun, $kegiatanId, $subKegiatanId);

        return [
            'plans'             => $plans,
            'groups'            => $groups,
            'totalRencana'      => $totalRencana,
            'totalTuPlan'       => $totalTuPlan,
            'totalPagu'         => $totalPagu,
            'totalRealisasi'    => $totalRealisasi,
            'totalSisaKapasitas' => $totalSisaKapasitas,
            'selesaiList'       => $selesaiList,
            'belumDirencanakan' => $belumDirencanakan,
            'belumCount'        => count($belumDirencanakan),
        ];
    }

    public function export(): void
    {
        $this->requireAdmin();

        [$tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q, $status] = $this->filtersFromQuery();

        try {
            $report = $this->buildExportReport($tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q, $status);
            (new RencanaPenyerapanExportService())->download($report);
        } catch (\Throwable $e) {
            error_log('Export rencana penyerapan gagal: ' . $e->getMessage());
            if (!headers_sent()) {
                $this->redirectWithMessage(
                    base_url('rencana-penyerapan?tahun=' . $tahun),
                    'error',
                    'Export Excel gagal dibuat. Silakan coba lagi.'
                );
            }
        }
    }

    /**
     * Rekap satu baris per rekening + detail bentuk kegiatan, mengikuti filter halaman.
     */
    private function buildExportReport(
        int $tahun,
        ?int $kegiatanId,
        ?int $subKegiatanId,
        ?string $mekanisme,
        string $q,
        string $status
    ): array {
        $data = $this->loadRencanaData($tahun, $kegiatanId, $subKegiatanId, $mekanisme, $q);
        $groups = $data['groups'];

        if ($status === 'selesai') {
            $groups = array_values(array_filter($groups, fn($g) => !empty($g['selesai'])));
        } elseif ($status === 'sudah') {
            $groups = array_values(array_filter($groups, fn($g) => empty($g['selesai'])));
        } elseif ($status === 'belum') {
            $groups = [];
        }

        $rows = [];
        $seen = [];

        foreach ($groups as $group) {
            $rekeningId = (int) $group['rekening_id'];
            $seen[$rekeningId] = true;

            $rencanaManual = (float) $group['total_rencana'];
            $rencanaTu = (float) $group['tu_plan'];
            $totalRencana = $rencanaManual + $rencanaTu;
            $realisasi = (float) $group['realisasi']['total'];
            $pagu = (float) $group['pagu'];

            $parts = [];
            if (count($group['plans']) > 0) {
                $parts[] = count($group['plans']) . ' bentuk kegiatan';
            }
            if ($rencanaTu > 0) {
                $parts[] = 'termasuk TU otomatis Rp ' . number_format($rencanaTu, 0, ',', '.');
            }
            if (!empty($group['selesai_manual'])) {
                $parts[] = 'ditandai selesai';
            } elseif (!empty($group['selesai_otomatis'])) {
                $parts[] = 'sisa kapasitas 0';
            }
            if (empty($parts)) {
                $parts[] = 'Belum ada rincian bentuk kegiatan';
            }

            $rows[] = [
                'kode_program'      => $group['kode_program'],
                'nama_program'      => $group['nama_program'],
                'kode_kegiatan'     => $group['kode_kegiatan'],
                'nama_kegiatan'     => $group['nama_kegiatan'],
                'kode_sub_kegiatan' => $group['kode_sub_kegiatan'],
                'nama_sub_kegiatan' => $group['nama_sub_kegiatan'],
                'nama_seksi'        => $group['nama_seksi'] ?? '',
                'kode_rekening'     => $group['kode_rekening'],
                'nama_rekening'     => $group['nama_rekening'],
                'pagu'              => $pagu,
                'rencana_manual'    => $rencanaManual,
                'rencana_tu'        => $rencanaTu,
                'total_rencana'     => $totalRencana,
                'realisasi'         => $realisasi,
                'sisa'              => $pagu - $realisasi - $totalRencana,
                'status'            => !empty($group['selesai_manual'])
                    ? 'Selesai'
                    : (!empty($group['selesai_otomatis']) ? 'Selesai (sisa 0)' : 'Sudah ada rencana'),
                'keterangan'        => implode('; ', $parts),
            ];
        }

        if ($status === '' || $status === 'belum') {
            foreach ($data['belumDirencanakan'] as $rek) {
                $rekeningId = (int) $rek['id'];
                if (isset($seen[$rekeningId])) {
                    continue;
                }
                $seen[$rekeningId] = true;

                $pagu = (float) $rek['nilai_pagu'];
                $realisasi = (float) ($rek['total_realisasi'] ?? 0);
                $tuSelesaiCount = (int) ($rek['tu_selesai_count'] ?? 0);
                $tuDitolakCount = (int) ($rek['tu_ditolak_count'] ?? 0);
                $tuSelesaiTotal = (float) ($rek['tu_selesai_total'] ?? 0);
                $tuDitolakTotal = (float) ($rek['tu_ditolak_total'] ?? 0);

                $parts = [];
                if ($realisasi > 0.005) {
                    $parts[] = 'Realisasi Rp ' . number_format($realisasi, 0, ',', '.');
                }
                if ($tuSelesaiCount > 0) {
                    $parts[] = 'TU selesai' . ($tuSelesaiCount > 1 ? ' (' . $tuSelesaiCount . ' pengajuan)' : '')
                        . ' Rp ' . number_format($tuSelesaiTotal, 0, ',', '.');
                }
                if ($tuDitolakCount > 0) {
                    $parts[] = 'TU ditolak' . ($tuDitolakCount > 1 ? ' (' . $tuDitolakCount . ' pengajuan)' : '')
                        . ' Rp ' . number_format($tuDitolakTotal, 0, ',', '.');
                }
                if (empty($parts)) {
                    $parts[] = 'Belum ada realisasi/TU';
                }

                $rows[] = [
                    'kode_program'      => $rek['kode_program'],
                    'nama_program'      => $rek['nama_program'],
                    'kode_kegiatan'     => $rek['kode_kegiatan'],
                    'nama_kegiatan'     => $rek['nama_kegiatan'],
                    'kode_sub_kegiatan' => $rek['kode_sub_kegiatan'],
                    'nama_sub_kegiatan' => $rek['nama_sub_kegiatan'],
                    'nama_seksi'        => $rek['nama_seksi'] ?? '',
                    'kode_rekening'     => $rek['kode_rekening'],
                    'nama_rekening'     => $rek['nama_rekening'],
                    'pagu'              => $pagu,
                    'rencana_manual'    => 0.0,
                    'rencana_tu'        => 0.0,
                    'total_rencana'     => 0.0,
                    'realisasi'         => $realisasi,
                    'sisa'              => $pagu - $realisasi,
                    'status'            => 'Belum ada rencana',
                    'keterangan'        => implode('; ', $parts),
                ];
            }
        }

        if ($status === '' || $status === 'selesai') {
            foreach ($data['selesaiList'] as $rek) {
                $rekeningId = (int) $rek['id'];
                if (isset($seen[$rekeningId])) {
                    continue;
                }
                $seen[$rekeningId] = true;

                $pagu = (float) $rek['total_pagu'];
                $rencanaManual = (float) $rek['total_rencana'];
                $rencanaTu = (float) $rek['total_tu'];
                $realisasi = (float) $rek['total_realisasi'];
                $keterangan = 'Ditandai selesai';
                if (!empty($rek['keterangan_selesai'])) {
                    $keterangan .= ' — ' . $rek['keterangan_selesai'];
                }

                $rows[] = [
                    'kode_program'      => $rek['kode_program'],
                    'nama_program'      => $rek['nama_program'],
                    'kode_kegiatan'     => $rek['kode_kegiatan'],
                    'nama_kegiatan'     => $rek['nama_kegiatan'],
                    'kode_sub_kegiatan' => $rek['kode_sub_kegiatan'],
                    'nama_sub_kegiatan' => $rek['nama_sub_kegiatan'],
                    'nama_seksi'        => $rek['nama_seksi'] ?? '',
                    'kode_rekening'     => $rek['kode_rekening'],
                    'nama_rekening'     => $rek['nama_rekening'],
                    'pagu'              => $pagu,
                    'rencana_manual'    => $rencanaManual,
                    'rencana_tu'        => $rencanaTu,
                    'total_rencana'     => $rencanaManual + $rencanaTu,
                    'realisasi'         => $realisasi,
                    'sisa'              => $pagu - $realisasi - $rencanaManual - $rencanaTu,
                    'status'            => 'Selesai',
                    'keterangan'        => $keterangan,
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            return strnatcmp($a['kode_program'], $b['kode_program'])
                ?: strnatcmp($a['kode_kegiatan'], $b['kode_kegiatan'])
                ?: strnatcmp($a['kode_sub_kegiatan'], $b['kode_sub_kegiatan'])
                ?: strnatcmp($a['kode_rekening'], $b['kode_rekening']);
        });

        $detail = [];
        foreach ($groups as $group) {
            foreach ($group['plans'] as $plan) {
                $detail[] = [
                    'kode_rekening'     => $group['kode_rekening'],
                    'nama_rekening'     => $group['nama_rekening'],
                    'bentuk_kegiatan'   => $plan['bentuk_kegiatan'],
                    'mekanisme'         => $plan['mekanisme'],
                    'bulan_mulai'       => (int) $plan['bulan_mulai'],
                    'bulan_selesai'     => (int) $plan['bulan_selesai'],
                    'nilai'             => (float) $plan['nilai_rencana'],
                    'keterangan'        => $plan['keterangan'] ?? '',
                    'sumber'            => 'Manual',
                ];
            }

            $tuPlan = (float) ($group['tu_plan'] ?? 0);
            if ($tuPlan > 0) {
                $bulanTu = array_keys(array_filter(
                    $group['tu_bulanan'] ?? [],
                    fn($nilai) => (float) $nilai > 0
                ));
                sort($bulanTu);
                $detail[] = [
                    'kode_rekening'     => $group['kode_rekening'],
                    'nama_rekening'     => $group['nama_rekening'],
                    'bentuk_kegiatan'   => 'Rencana otomatis dari pengajuan TU',
                    'mekanisme'         => 'TU',
                    'bulan_mulai'       => $bulanTu ? (int) min($bulanTu) : null,
                    'bulan_selesai'     => $bulanTu ? (int) max($bulanTu) : null,
                    'nilai'             => $tuPlan,
                    'keterangan'        => 'Pengajuan TU berjalan dihitung sebagai rencana rekening ini',
                    'sumber'            => 'TU otomatis',
                ];
            }
        }

        $totals = [
            'count'          => count($rows),
            'pagu'           => 0.0,
            'rencana_manual' => 0.0,
            'rencana_tu'     => 0.0,
            'total_rencana'  => 0.0,
            'realisasi'      => 0.0,
            'sisa'           => 0.0,
        ];
        $statusGroups = [];
        foreach ($rows as $row) {
            foreach (['pagu', 'rencana_manual', 'rencana_tu', 'total_rencana', 'realisasi', 'sisa'] as $key) {
                $totals[$key] += (float) $row[$key];
            }
            $label = $row['status'];
            if (!isset($statusGroups[$label])) {
                $statusGroups[$label] = [
                    'count'          => 0,
                    'pagu'           => 0.0,
                    'rencana_manual' => 0.0,
                    'rencana_tu'     => 0.0,
                    'total_rencana'  => 0.0,
                    'realisasi'      => 0.0,
                    'sisa'           => 0.0,
                ];
            }
            $statusGroups[$label]['count']++;
            foreach (['pagu', 'rencana_manual', 'rencana_tu', 'total_rencana', 'realisasi', 'sisa'] as $key) {
                $statusGroups[$label][$key] += (float) $row[$key];
            }
        }

        $orderedStatusGroups = [];
        foreach (['Sudah ada rencana', 'Belum ada rencana', 'Selesai', 'Selesai (sisa 0)'] as $label) {
            if (isset($statusGroups[$label])) {
                $orderedStatusGroups[$label] = $statusGroups[$label];
            }
        }

        $mekanismeLabels = ['GU' => 'GU — Ganti Uang', 'TU' => 'TU — Tambah Uang', 'LS' => 'LS — Langsung'];
        $statusLabels = ['selesai' => 'Selesai', 'sudah' => 'Sudah ada rencana', 'belum' => 'Belum ada rencana'];

        $kegiatanLabel = 'Semua kegiatan';
        if ($kegiatanId !== null) {
            foreach ($this->kegiatanModel->getAll() as $kg) {
                if ((int) $kg['id'] === $kegiatanId) {
                    $kegiatanLabel = $kg['kode_kegiatan'] . ' — ' . $kg['nama_kegiatan'];
                    break;
                }
            }
        }
        $subKegiatanLabel = 'Semua sub kegiatan';
        if ($subKegiatanId !== null) {
            foreach ($this->subKegiatanModel->getAll() as $sk) {
                if ((int) $sk['id'] === $subKegiatanId) {
                    $subKegiatanLabel = $sk['kode_sub_kegiatan'] . ' — ' . $sk['nama_sub_kegiatan'];
                    break;
                }
            }
        }

        $filterParts = [
            'Kegiatan: ' . $kegiatanLabel,
            'Sub kegiatan: ' . $subKegiatanLabel,
            'Mekanisme: ' . ($mekanisme !== null ? ($mekanismeLabels[$mekanisme] ?? $mekanisme) : 'Semua'),
            'Status: ' . ($status !== '' ? ($statusLabels[$status] ?? $status) : 'Semua'),
        ];
        if ($q !== '') {
            $filterParts[] = 'Pencarian: "' . $q . '"';
        }

        return [
            'tahun'         => $tahun,
            'rows'          => $rows,
            'detail'        => $detail,
            'totals'        => $totals,
            'statusGroups'  => $orderedStatusGroups,
            'filterText'    => implode('   •   ', $filterParts),
            'generatedAt'   => date('d-m-Y H:i'),
        ];
    }

    public function create(): void
    {
        $this->requireAdmin();

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $rekeningId = (int) ($_GET['rekening_id'] ?? 0);

        if ($rekeningId > 0 && $this->rekeningModel->getById($rekeningId)) {
            $_SESSION['rp_inline'] = [
                'action' => 'store',
                'form' => [
                    'id'              => null,
                    'rekening_id'     => $rekeningId,
                    'tahun'           => $tahun,
                    'bentuk_kegiatan' => '',
                    'mekanisme'       => '',
                    'bulan_mulai'     => (int) date('n'),
                    'bulan_selesai'   => (int) date('n'),
                    'nilai_rencana'   => '',
                    'keterangan'      => '',
                ],
                'errors' => [],
            ];
            header('Location: ' . base_url('rencana-penyerapan?tahun=' . $tahun) . '#rek-' . $rekeningId);
            exit;
        }

        $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'info', 'Pilih rekening lalu klik Buat pada kolom Aksi.');
    }

    public function store(): void
    {
        $this->requireAdmin();

        $form = $this->collectForm($_POST);
        $errors = $this->validate($form);

        if ($errors) {
            $this->redirectInlineForm('store', $form, $errors);
            return;
        }

        try {
            $this->model->create($form, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            $this->flashAfterSave($form, 'Rencana penyerapan berhasil disimpan.');
            header('Location: ' . $this->indexUrl($form));
            exit;
        } catch (\Throwable $e) {
            error_log('Gagal menyimpan rencana penyerapan: ' . $e->getMessage());
            $this->redirectInlineForm('store', $form, ['umum' => 'Gagal menyimpan rencana: ' . $e->getMessage()]);
        }
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();

        $plan = $this->model->getById($id);
        if (!$plan) {
            $this->redirectWithMessage(base_url('rencana-penyerapan'), 'error', 'Rencana tidak ditemukan.');
            return;
        }

        $_SESSION['rp_inline'] = [
            'action' => 'update',
            'form'   => $this->formFromPlan($plan),
            'errors' => [],
        ];
        header('Location: ' . base_url('rencana-penyerapan?tahun=' . (int) $plan['tahun']) . '#rek-' . (int) $plan['rekening_id']);
        exit;
    }

    public function update(int $id): void
    {
        $this->requireAdmin();

        $existing = $this->model->getById($id);
        if (!$existing) {
            $this->redirectWithMessage(base_url('rencana-penyerapan'), 'error', 'Rencana tidak ditemukan.');
            return;
        }

        $form = $this->collectForm($_POST);
        $form['id'] = $id;
        $errors = $this->validate($form, $id);

        if ($errors) {
            $this->redirectInlineForm('update', $form, $errors);
            return;
        }

        try {
            $this->model->update($id, $form);
            $this->flashAfterSave($form, 'Rencana penyerapan berhasil diperbarui.', $id);
            header('Location: ' . $this->indexUrl($form));
            exit;
        } catch (\Throwable $e) {
            error_log('Gagal memperbarui rencana penyerapan: ' . $e->getMessage());
            $this->redirectInlineForm('update', $form, ['umum' => 'Gagal memperbarui rencana: ' . $e->getMessage()]);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();

        try {
            $plan = $this->model->getById($id);
            $this->model->delete($id);
            $url = base_url('rencana-penyerapan?tahun=' . urlencode((string) ($plan['tahun'] ?? date('Y'))));
            $this->redirectWithMessage($url, 'success', 'Rencana penyerapan berhasil dihapus.');
        } catch (\Throwable $e) {
            error_log('Gagal menghapus rencana penyerapan: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('rencana-penyerapan'), 'error', 'Gagal menghapus rencana.');
        }
    }

    public function markSelesai(): void
    {
        $this->requireAdmin();

        $rekeningId = (int) ($_POST['rekening_id'] ?? 0);
        $tahun = (int) ($_POST['tahun'] ?? date('Y'));
        $back = $this->safeBackQuery();

        if ($rekeningId <= 0 || !$this->rekeningModel->getById($rekeningId)) {
            $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'error', 'Rekening tidak ditemukan.');
        }

        try {
            $this->model->markSelesai($rekeningId, $tahun, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            $url = base_url('rencana-penyerapan' . ($back !== '' ? '?' . $back : '?tahun=' . $tahun) . '#rek-' . $rekeningId);
            $this->redirectWithMessage($url, 'success', 'Rekening ditandai selesai. Tidak perlu input rencana lagi.');
        } catch (\Throwable $e) {
            error_log('Gagal menandai rekening selesai: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'error', 'Gagal menandai rekening selesai.');
        }
    }

    public function batalSelesai(): void
    {
        $this->requireAdmin();

        $rekeningId = (int) ($_POST['rekening_id'] ?? 0);
        $tahun = (int) ($_POST['tahun'] ?? date('Y'));
        $back = $this->safeBackQuery();

        try {
            $this->model->unmarkSelesai($rekeningId, $tahun);
            $url = base_url('rencana-penyerapan' . ($back !== '' ? '?' . $back : '?tahun=' . $tahun) . '#rek-' . $rekeningId);
            $this->redirectWithMessage($url, 'success', 'Status selesai dibatalkan. Rekening perlu rencana penyerapan kembali.');
        } catch (\Throwable $e) {
            error_log('Gagal membatalkan status selesai: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'error', 'Gagal membatalkan status selesai.');
        }
    }

    public function rekeningInfo(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $rekeningId = (int) ($_GET['rekening_id'] ?? 0);
        $tahun = (int) ($_GET['tahun'] ?? 0);
        $excludeId = isset($_GET['exclude_id']) && $_GET['exclude_id'] !== '' ? (int) $_GET['exclude_id'] : null;

        if ($rekeningId <= 0 || $tahun <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'rekening_id dan tahun wajib diisi.']);
            return;
        }

        $rekening = $this->rekeningModel->getById($rekeningId);
        if (!$rekening) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Rekening tidak ditemukan.']);
            return;
        }

        $pagu = $this->model->getPaguForRekening($rekeningId, $tahun);
        $totalRencana = $this->model->getTotalByRekeningAndYear($rekeningId, $tahun, $excludeId);
        $tuPlan = $this->model->getTuPlanForRekening($rekeningId, $tahun);
        $realisasi = $this->model->getRealisasiForRekening($rekeningId, $tahun);
        $realisasiBulanan = $this->model->getRealisasiMonthlyForRekening($rekeningId, $tahun);
        $totalRak = $this->rakModel->getTotalByRekeningAndYear($rekeningId, $tahun);
        $selesaiMap = $this->model->getSelesaiMap($tahun);
        $selesai = isset($selesaiMap[$rekeningId]);

        echo json_encode([
            'ok' => true,
            'rekening' => [
                'kode_rekening' => $rekening['kode_rekening'],
                'nama_rekening' => $rekening['nama_rekening'],
                'nama_seksi'    => $rekening['nama_seksi'] ?? null,
            ],
            'pagu'              => $pagu,
            'total_rencana'     => $totalRencana,
            'total_tu'          => $tuPlan,
            'selesai'           => $selesai,
            'sisa_rencana'      => $pagu !== null ? $pagu - $totalRencana - $tuPlan : null,
            'sisa_kapasitas'    => $pagu !== null ? $pagu - (float) $realisasi['total'] - $totalRencana - $tuPlan : null,
            'total_rak'         => $totalRak,
            'realisasi'         => $realisasi,
            'realisasi_bulanan' => $realisasiBulanan,
        ]);
    }

    private function collectForm(array $input): array
    {
        $mekanisme = strtoupper(trim((string) ($input['mekanisme'] ?? '')));
        if (!in_array($mekanisme, ['GU', 'TU', 'LS'], true)) {
            $mekanisme = '';
        }

        return [
            'id'              => isset($input['id']) && $input['id'] !== '' ? (int) $input['id'] : null,
            'rekening_id'     => (int) ($input['rekening_id'] ?? 0),
            'tahun'           => (int) ($input['tahun'] ?? date('Y')),
            'bentuk_kegiatan' => trim((string) ($input['bentuk_kegiatan'] ?? '')),
            'mekanisme'       => $mekanisme,
            'bulan_mulai'     => (int) ($input['bulan_mulai'] ?? 1),
            'bulan_selesai'   => (int) ($input['bulan_selesai'] ?? 1),
            'nilai_rencana'   => $this->normalizeNilai((string) ($input['nilai_rencana'] ?? '')),
            'keterangan'      => trim((string) ($input['keterangan'] ?? '')),
        ];
    }

    private function normalizeNilai(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }
        $normalized = str_replace([',', '.'], '', $value);
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['rekening_id'] <= 0 || !$this->rekeningModel->getById((int) $data['rekening_id'])) {
            $errors['rekening_id'] = 'Rekening wajib dipilih dan harus valid.';
        }
        if ($data['tahun'] < 2000 || $data['tahun'] > 2100) {
            $errors['tahun'] = 'Tahun harus antara 2000 dan 2100.';
        }
        if ($data['bentuk_kegiatan'] === '') {
            $errors['bentuk_kegiatan'] = 'Bentuk kegiatan wajib diisi.';
        } elseif (mb_strlen($data['bentuk_kegiatan']) > 150) {
            $errors['bentuk_kegiatan'] = 'Bentuk kegiatan maksimal 150 karakter.';
        }
        if (mb_strlen((string) $data['keterangan']) > 255) {
            $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';
        }
        if (!in_array($data['mekanisme'], ['GU', 'TU', 'LS'], true)) {
            $errors['mekanisme'] = 'Pilih salah satu mekanisme pembayaran.';
        }
        if ($data['bulan_mulai'] < 1 || $data['bulan_mulai'] > 12) {
            $errors['bulan_mulai'] = 'Bulan mulai tidak valid.';
        }
        if ($data['bulan_selesai'] < 1 || $data['bulan_selesai'] > 12) {
            $errors['bulan_selesai'] = 'Bulan selesai tidak valid.';
        }
        if ($data['bulan_mulai'] > $data['bulan_selesai']) {
            $errors['bulan_selesai'] = 'Bulan selesai tidak boleh sebelum bulan mulai.';
        }
        if ($data['nilai_rencana'] <= 0) {
            $errors['nilai_rencana'] = 'Nilai rencana harus lebih dari 0.';
        } elseif ($data['rekening_id'] > 0 && empty($errors['rekening_id'])) {
            $pagu = $this->model->getPaguForRekening((int) $data['rekening_id'], (int) $data['tahun']);
            if ($pagu !== null) {
                $totalRencana = $this->model->getTotalByRekeningAndYear((int) $data['rekening_id'], (int) $data['tahun'], $excludeId);
                $tuPlan = $this->model->getTuPlanForRekening((int) $data['rekening_id'], (int) $data['tahun']);
                $realisasi = $this->model->getRealisasiForRekening((int) $data['rekening_id'], (int) $data['tahun']);
                $sisaKapasitas = (float) $pagu - (float) $realisasi['total'] - $totalRencana - $tuPlan;
                if ($data['nilai_rencana'] > $sisaKapasitas + 0.005) {
                    $errors['nilai_rencana'] = 'Nilai rencana melebihi sisa kapasitas (Rp '
                        . number_format(max(0, $sisaKapasitas), 0, ',', '.') . ').';
                }
            }

            if ($excludeId === null) {
                $selesaiMap = $this->model->getSelesaiMap((int) $data['tahun']);
                if (isset($selesaiMap[(int) $data['rekening_id']])) {
                    $errors['rekening_id'] = 'Rekening ini sudah ditandai selesai. Batalkan status selesai bila perlu membuat rencana baru.';
                }
            }
        }

        return $errors;
    }

    private function formFromPlan(array $plan): array
    {
        return [
            'id'              => (int) $plan['id'],
            'rekening_id'     => (int) $plan['rekening_id'],
            'tahun'           => (int) $plan['tahun'],
            'bentuk_kegiatan' => $plan['bentuk_kegiatan'],
            'mekanisme'       => $plan['mekanisme'],
            'bulan_mulai'     => (int) $plan['bulan_mulai'],
            'bulan_selesai'   => (int) $plan['bulan_selesai'],
            'nilai_rencana'   => (float) $plan['nilai_rencana'],
            'keterangan'      => $plan['keterangan'] ?? '',
        ];
    }

    private function redirectInlineForm(string $action, array $form, array $errors): void
    {
        $rekeningId = (int) ($form['rekening_id'] ?? 0);
        $tahun = (int) ($form['tahun'] ?? date('Y'));

        if ($rekeningId <= 0) {
            $message = reset($errors) ?: 'Data rencana tidak valid.';
            $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'error', (string) $message);
            return;
        }

        $_SESSION['rp_inline'] = [
            'action' => $action,
            'form'   => $form,
            'errors' => $errors,
        ];

        $url = base_url('rencana-penyerapan?tahun=' . $tahun) . '#rek-' . $rekeningId;
        header('Location: ' . $url);
        exit;
    }

    private function safeBackQuery(): string
    {
        $back = trim((string) ($_POST['back'] ?? ''));
        return preg_match('/^[A-Za-z0-9_=&%+.\-]*$/', $back) ? $back : '';
    }

    private function flashAfterSave(array $form, string $message, ?int $excludeId = null): void
    {
        $pagu = $this->model->getPaguForRekening((int) $form['rekening_id'], (int) $form['tahun']);
        $total = $this->model->getTotalByRekeningAndYear((int) $form['rekening_id'], (int) $form['tahun'], $excludeId);
        $tuPlan = $this->model->getTuPlanForRekening((int) $form['rekening_id'], (int) $form['tahun']);
        $realisasi = $this->model->getRealisasiForRekening((int) $form['rekening_id'], (int) $form['tahun']);
        $terpakai = $total + $tuPlan + (float) $realisasi['total'];

        if ($pagu !== null && $terpakai > $pagu) {
            $_SESSION['flash_message'] = $message . ' Perhatian: total rencana + rencana TU + realisasi (Rp ' . number_format($terpakai, 0, ',', '.')
                . ') melebihi pagu rekening (Rp ' . number_format($pagu, 0, ',', '.') . ').';
            $_SESSION['flash_type'] = 'warning';
            return;
        }

        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'success';
    }

    private function indexUrl(array $form): string
    {
        return base_url('rencana-penyerapan?tahun=' . urlencode((string) $form['tahun']) . '#rek-' . (int) $form['rekening_id']);
    }

    private function redirectWithMessage(string $url, string $type, string $message): void
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header('Location: ' . $url);
        exit;
    }
}
