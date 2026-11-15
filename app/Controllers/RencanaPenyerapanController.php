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

/**
 * Perencanaan timeline penyerapan anggaran per kode rekening.
 *
 * Routes:
 *   GET  /rencana-penyerapan                 → index()       : timeline per rekening
 *   GET  /rencana-penyerapan/create          → create()      : form rencana baru
 *   POST /rencana-penyerapan/store           → store()
 *   GET  /rencana-penyerapan/edit/{id}       → edit()
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

    public function index(): void
    {
        $this->requireAdmin();

        $tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y');
        $kegiatanId = isset($_GET['kegiatan_id']) && $_GET['kegiatan_id'] !== '' ? (int) $_GET['kegiatan_id'] : null;
        $subKegiatanId = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;
        $mekanisme = strtoupper(trim((string) ($_GET['mekanisme'] ?? '')));
        if (!in_array($mekanisme, ['GU', 'TU', 'LS'], true)) {
            $mekanisme = null;
        }
        $q = trim((string) ($_GET['q'] ?? ''));

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
        $belumCount = count($belumDirencanakan);
        $belumDirencanakan = array_slice($belumDirencanakan, 0, 100);

        $page = max(1, (int) ($_GET['page'] ?? 1));
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
            'q'               => $q,
        ], fn($v) => $v !== null && $v !== '');

        $filterKegiatanList = $this->kegiatanModel->getAll();
        $filterSubKegiatanList = $this->subKegiatanModel->getAll();
        $filterKegiatan = $kegiatanId;
        $filterSubKegiatan = $subKegiatanId;
        $filterMekanisme = $mekanisme;
        $filterQ = $q;

        $pageTitle = 'Rencana Penyerapan';
        $activePage = 'rencana_penyerapan';
        $viewFile = __DIR__ . '/../../views/rencana_penyerapan/index.php';

        include __DIR__ . '/../../views/layout.php';
    }

    public function create(): void
    {
        $this->requireAdmin();

        $form = [
            'id'               => null,
            'rekening_id'      => 0,
            'tahun'            => isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : (int) date('Y'),
            'bentuk_kegiatan'  => '',
            'mekanisme'        => 'GU',
            'bulan_mulai'      => (int) date('n'),
            'bulan_selesai'    => (int) date('n'),
            'nilai_rencana'    => '',
            'keterangan'       => '',
        ];

        $rekeningId = (int) ($_GET['rekening_id'] ?? 0);
        if ($rekeningId > 0 && $this->rekeningModel->getById($rekeningId)) {
            $form['rekening_id'] = $rekeningId;
        }

        $this->showForm('Tambah Rencana Penyerapan', 'Tentukan bentuk kegiatan, mekanisme pembayaran, dan periode penyerapan.', $form, [], 'store');
    }

    public function store(): void
    {
        $this->requireAdmin();

        $form = $this->collectForm($_POST);
        $errors = $this->validate($form);

        if ($errors) {
            $this->showForm('Tambah Rencana Penyerapan', 'Periksa kembali data rencana.', $form, $errors, 'store');
            return;
        }

        try {
            $this->model->create($form, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            $this->flashAfterSave($form, 'Rencana penyerapan berhasil disimpan.');
            header('Location: ' . $this->indexUrl($form));
            exit;
        } catch (\Throwable $e) {
            error_log('Gagal menyimpan rencana penyerapan: ' . $e->getMessage());
            $this->showForm('Tambah Rencana Penyerapan', 'Gagal menyimpan rencana.', $form, ['umum' => 'Gagal menyimpan rencana: ' . $e->getMessage()], 'store');
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

        $form = [
            'id'               => (int) $plan['id'],
            'rekening_id'      => (int) $plan['rekening_id'],
            'tahun'            => (int) $plan['tahun'],
            'bentuk_kegiatan'  => $plan['bentuk_kegiatan'],
            'mekanisme'        => $plan['mekanisme'],
            'bulan_mulai'      => (int) $plan['bulan_mulai'],
            'bulan_selesai'    => (int) $plan['bulan_selesai'],
            'nilai_rencana'    => (float) $plan['nilai_rencana'],
            'keterangan'       => $plan['keterangan'] ?? '',
        ];

        $this->showForm('Edit Rencana Penyerapan', 'Ubah detail rencana kegiatan dan periode penyerapan.', $form, [], 'update');
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
        $errors = $this->validate($form, $id);

        if ($errors) {
            $this->showForm('Edit Rencana Penyerapan', 'Periksa kembali data rencana.', $form, $errors, 'update');
            return;
        }

        try {
            $this->model->update($id, $form);
            $this->flashAfterSave($form, 'Rencana penyerapan berhasil diperbarui.');
            header('Location: ' . $this->indexUrl($form));
            exit;
        } catch (\Throwable $e) {
            error_log('Gagal memperbarui rencana penyerapan: ' . $e->getMessage());
            $this->showForm('Edit Rencana Penyerapan', 'Gagal memperbarui rencana.', $form, ['umum' => 'Gagal memperbarui rencana: ' . $e->getMessage()], 'update');
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
        $redirect = (string) ($_POST['redirect'] ?? 'index');

        if ($rekeningId <= 0 || !$this->rekeningModel->getById($rekeningId)) {
            $this->redirectWithMessage(base_url('rencana-penyerapan?tahun=' . $tahun), 'error', 'Rekening tidak ditemukan.');
        }

        try {
            $this->model->markSelesai($rekeningId, $tahun, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            $url = $redirect === 'create'
                ? base_url('rencana-penyerapan/create?tahun=' . $tahun)
                : base_url('rencana-penyerapan?tahun=' . $tahun . '#rek-' . $rekeningId);
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
        $redirect = (string) ($_POST['redirect'] ?? 'index');

        try {
            $this->model->unmarkSelesai($rekeningId, $tahun);
            $url = $redirect === 'create'
                ? base_url('rencana-penyerapan/create?tahun=' . $tahun)
                : base_url('rencana-penyerapan?tahun=' . $tahun . '#rek-' . $rekeningId);
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
        $mekanisme = strtoupper(trim((string) ($input['mekanisme'] ?? 'GU')));
        if (!in_array($mekanisme, ['GU', 'TU', 'LS'], true)) {
            $mekanisme = 'GU';
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
            $errors['mekanisme'] = 'Mekanisme pembayaran tidak valid.';
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
        }

        return $errors;
    }

    private function showForm(string $title, string $subtitle, array $form, array $errors, string $action): void
    {
        $isEdit = $action === 'update';
        $formTahun = (int) ($form['tahun'] ?? date('Y'));

        $rekenings = $isEdit ? [] : $this->model->getRekeningPickerList($formTahun);
        $selectedRekening = (int) ($form['rekening_id'] ?? 0) > 0
            ? $this->rekeningModel->getById((int) $form['rekening_id'])
            : null;

        $pageTitle = $title;
        $activePage = 'rencana_penyerapan';
        $viewFile = __DIR__ . '/../../views/rencana_penyerapan/form.php';

        include __DIR__ . '/../../views/layout.php';
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
