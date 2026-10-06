# Export RAK ke Excel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur Export RAK bulanan ke file format Excel (.xlsx) pada menu RAK sesuai filter aktif (tahun, kegiatan, sub kegiatan).

**Architecture:** Route `/rak/export` diproses oleh method `RakController::export()`. Controller membaca parameter filter dari query string, memuat data RAK menggunakan `Rak::getWithFilters()`, mengelompokkan per rekening dan menyusun nilai 12 bulan serta totalnya, lalu menyusun worksheet menggunakan `PhpOffice\PhpSpreadsheet` dengan styling profesional sebelum diunduh sebagai file `.xlsx`. Pada view `views/rak/index.php`, ditambahkan tombol "Export Excel" di samping tombol "Tambah RAK".

**Tech Stack:** PHP 8.3, PhpOffice\PhpSpreadsheet, Bootstrap 5 Icons, MySQL / PDO.

## Global Constraints
- Target Menu: Menu RAK (`/rak`).
- Format File: Microsoft Excel (.xlsx) dengan PhpSpreadsheet.
- Filter Preservation: Ekspor harus mematuhi filter yang sedang aktif (`tahun`, `kegiatan_id`, `sub_kegiatan_id`).
- Scope Data: Seluruh baris yang cocok dengan filter (tidak terpotong paginasi 10 baris).

---

### Task 1: Test Generator & Logic Export RAK

**Files:**
- Create: `tests/test_rak_excel_export.php`

**Interfaces:**
- Consumes: `App\Models\Rak`, `App\Controllers\RakController`
- Produces: Test script yang memverifikasi logika grouping RAK dan pembuatan Spreadsheet PhpOffice tanpa error.

- [ ] **Step 1: Tulis skrip tes verifikasi export RAK**

Buat file `tests/test_rak_excel_export.php`:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Controllers/RakController.php';

use App\Models\Rak;
use App\Models\Pagu;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use App\Controllers\RakController;

$db = Database::getConnection();
$rakModel = new Rak($db);
$paguModel = new Pagu($db);
$programModel = new Program($db);
$kegiatanModel = new Kegiatan($db);
$subKegiatanModel = new SubKegiatan($db);
$rekeningModel = new Rekening($db);

$controller = new RakController(
    $rakModel,
    $paguModel,
    $programModel,
    $kegiatanModel,
    $subKegiatanModel,
    $rekeningModel
);

// Verifikasi method export ada di RakController
$reflection = new ReflectionClass($controller);
assert($reflection->hasMethod('export'), 'RakController harus memiliki method export()');

// Verifikasi method buildSpreadsheet ada untuk testing unit/isolasi output
assert($reflection->hasMethod('buildSpreadsheet'), 'RakController harus memiliki method buildSpreadsheet()');

$method = $reflection->getMethod('buildSpreadsheet');
$spreadsheet = $method->invoke($controller, null, null, null);

assert($spreadsheet instanceof \PhpOffice\PhpSpreadsheet\Spreadsheet, 'Output buildSpreadsheet harus instance dari Spreadsheet');

$sheet = $spreadsheet->getActiveSheet();
assert($sheet->getCell('A1')->getValue() === 'RENCANA ANGGARAN KAS (RAK)', 'Judul cell A1 harus RENCANA ANGGARAN KAS (RAK)');
assert($sheet->getCell('A5')->getValue() === 'No', 'Header A5 harus No');
assert($sheet->getCell('B5')->getValue() === 'Kode Rekening', 'Header B5 harus Kode Rekening');
assert($sheet->getCell('H5')->getValue() === 'Jan', 'Header H5 harus Jan');
assert($sheet->getCell('T5')->getValue() === 'Total (Rp)', 'Header T5 harus Total (Rp)');

echo "✓ Test RAK Excel Export berhasil!\n";
```

- [ ] **Step 2: Jalankan tes untuk memverifikasi kegagalan awal (Red Phase)**

Run: `php tests/test_rak_excel_export.php`
Expected: Error assertion gagal karena method `export` dan `buildSpreadsheet` belum ada di `RakController`.

- [ ] **Step 3: Commit tes awal**

```bash
git add tests/test_rak_excel_export.php
git commit -m "test: add test for RAK excel export logic"
```

---

### Task 2: Implementasi Method `export()` dan `buildSpreadsheet()` di `RakController`

**Files:**
- Modify: `app/Controllers/RakController.php`

**Interfaces:**
- Consumes: `Rak::getWithFilters(?int $tahun, ?int $kegiatanId, ?int $subKegiatanId)`, `PhpOffice\PhpSpreadsheet`
- Produces: `RakController::buildSpreadsheet(?int $tahun, ?int $kegiatanId, ?int $subKegiatanId): Spreadsheet`, `RakController::export(): void`

- [ ] **Step 1: Tambahkan use statements dan implementasi method di `RakController.php`**

Buka `app/Controllers/RakController.php`:
1. Tambahkan use statements untuk PhpSpreadsheet:
```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
```
2. Tambahkan method `buildSpreadsheet(?int $filterTahun, ?int $filterKegiatan, ?int $filterSubKegiatan): Spreadsheet`:
```php
    /**
     * Membangun objek Spreadsheet RAK berdasarkan filter.
     */
    public function buildSpreadsheet(?int $filterTahun = null, ?int $filterKegiatan = null, ?int $filterSubKegiatan = null): Spreadsheet {
        $raksFlat = $this->rakModel->getWithFilters($filterTahun, $filterKegiatan, $filterSubKegiatan);

        // Group by rekening_id + tahun
        $groupedRak = [];
        foreach ($raksFlat as $rak) {
            $key = $rak['rekening_id'] . '_' . $rak['tahun'];
            if (!isset($groupedRak[$key])) {
                $groupedRak[$key] = [
                    'rekening_id'       => $rak['rekening_id'],
                    'tahun'             => $rak['tahun'],
                    'kode_rekening'     => $rak['kode_rekening'],
                    'nama_rekening'     => $rak['nama_rekening'],
                    'kode_program'      => $rak['kode_program'] ?? '',
                    'nama_program'      => $rak['nama_program'] ?? '',
                    'kode_kegiatan'     => $rak['kode_kegiatan'] ?? '',
                    'nama_kegiatan'     => $rak['nama_kegiatan'] ?? '',
                    'kode_sub_kegiatan' => $rak['kode_sub_kegiatan'] ?? '',
                    'nama_sub_kegiatan' => $rak['nama_sub_kegiatan'] ?? '',
                    'months'            => array_fill(1, 12, 0),
                    'total'             => 0
                ];
            }
            $groupedRak[$key]['months'][$rak['bulan']] = (float) $rak['nilai_rak'];
            $groupedRak[$key]['total'] += (float) $rak['nilai_rak'];
        }
        $groupedRak = array_values($groupedRak);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAK');

        // 1. Header Metadata
        $sheet->setCellValue('A1', 'RENCANA ANGGARAN KAS (RAK)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $filterInfo = [];
        $filterInfo[] = 'Tahun: ' . ($filterTahun !== null ? $filterTahun : 'Semua');
        if ($filterKegiatan !== null) {
            $keg = $this->kegiatanModel->getById($filterKegiatan);
            if ($keg) $filterInfo[] = 'Kegiatan: ' . ($keg['kode_kegiatan'] . ' - ' . $keg['nama_kegiatan']);
        }
        if ($filterSubKegiatan !== null) {
            $subKeg = $this->subKegiatanModel->getById($filterSubKegiatan);
            if ($subKeg) $filterInfo[] = 'Sub Kegiatan: ' . ($subKeg['kode_sub_kegiatan'] . ' - ' . $subKeg['nama_sub_kegiatan']);
        }
        $sheet->setCellValue('A2', implode(' | ', $filterInfo));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->setCellValue('A3', 'Diunduh pada: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('A3')->getFont()->setSize(9)->getColor()->setRGB('666666');

        // 2. Table Headers (Row 5)
        $headers = [
            'A5' => 'No',
            'B5' => 'Kode Rekening',
            'C5' => 'Nama Rekening',
            'D5' => 'Program',
            'E5' => 'Kegiatan',
            'F5' => 'Sub Kegiatan',
            'G5' => 'Tahun',
            'H5' => 'Jan',
            'I5' => 'Feb',
            'J5' => 'Mar',
            'K5' => 'Apr',
            'L5' => 'Mei',
            'M5' => 'Jun',
            'N5' => 'Jul',
            'O5' => 'Agu',
            'P5' => 'Sep',
            'Q5' => 'Okt',
            'R5' => 'Nov',
            'S5' => 'Des',
            'T5' => 'Total (Rp)'
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ];
        $sheet->getStyle('A5:T5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // 3. Data Rows
        $row = 6;
        $numFmt = '#,##0';
        $no = 1;

        if (empty($groupedRak)) {
            $sheet->setCellValue("A{$row}", 'Tidak ada data RAK sesuai filter yang dipilih');
            $sheet->mergeCells("A{$row}:T{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:T{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CCCCCC');
            $row++;
        } else {
            foreach ($groupedRak as $item) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValueExplicit("B{$row}", $item['kode_rekening'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $item['nama_rekening']);
                $sheet->setCellValue("D{$row}", $item['kode_program'] . ' - ' . $item['nama_program']);
                $sheet->setCellValue("E{$row}", $item['kode_kegiatan'] . ' - ' . $item['nama_kegiatan']);
                $sheet->setCellValue("F{$row}", $item['kode_sub_kegiatan'] . ' - ' . $item['nama_sub_kegiatan']);
                $sheet->setCellValue("G{$row}", $item['tahun']);

                $colIndex = 'H';
                for ($m = 1; $m <= 12; $m++) {
                    $sheet->setCellValue("{$colIndex}{$row}", $item['months'][$m]);
                    $sheet->getStyle("{$colIndex}{$row}")->getNumberFormat()->setFormatCode($numFmt);
                    $colIndex++;
                }

                $sheet->setCellValue("T{$row}", "=SUM(H{$row}:S{$row})");
                $sheet->getStyle("T{$row}")->getNumberFormat()->setFormatCode($numFmt);

                // Alignments & border
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$row}:T{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$row}:T{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

                $row++;
            }

            // 4. Grand Total Row
            $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $lastDataRow = $row - 1;
            $colIndex = 'H';
            for ($m = 1; $m <= 12; $m++) {
                $sheet->setCellValue("{$colIndex}{$row}", "=SUM({$colIndex}6:{$colIndex}{$lastDataRow})");
                $sheet->getStyle("{$colIndex}{$row}")->getNumberFormat()->setFormatCode($numFmt);
                $sheet->getStyle("{$colIndex}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $colIndex++;
            }
            $sheet->setCellValue("T{$row}", "=SUM(T6:T{$lastDataRow})");
            $sheet->getStyle("T{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("T{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $totalStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF08A']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']]]
            ];
            $sheet->getStyle("A{$row}:T{$row}")->applyFromArray($totalStyle);
            $sheet->getRowDimension($row)->setRowHeight(22);
        }

        // Auto width for columns A to T
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
```
3. Tambahkan method `export(): void`:
```php
    /**
     * Download file Excel RAK sesuai filter.
     */
    public function export(): void {
        try {
            $filterTahun       = isset($_GET['tahun'])          && $_GET['tahun']          !== '' ? (int) $_GET['tahun']          : null;
            $filterKegiatan    = isset($_GET['kegiatan_id'])    && $_GET['kegiatan_id']    !== '' ? (int) $_GET['kegiatan_id']    : null;
            $filterSubKegiatan = isset($_GET['sub_kegiatan_id']) && $_GET['sub_kegiatan_id'] !== '' ? (int) $_GET['sub_kegiatan_id'] : null;

            $spreadsheet = $this->buildSpreadsheet($filterTahun, $filterKegiatan, $filterSubKegiatan);

            $tahunLabel = $filterTahun !== null ? (string)$filterTahun : 'Semua';
            $filename = 'RAK_' . $tahunLabel . '_' . date('Ymd_His') . '.xlsx';

            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            error_log('Error exporting RAK: ' . $e->getMessage());
            $this->redirectWithMessage(base_url('rak'), 'error', 'Gagal mengekspor data RAK: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 2: Jalankan tes unit untuk memastikan `RakController` lulus uji**

Run: `php tests/test_rak_excel_export.php`
Expected: `✓ Test RAK Excel Export berhasil!`

- [ ] **Step 3: Commit perubahan di `RakController.php`**

```bash
git add app/Controllers/RakController.php
git commit -m "feat: add export and buildSpreadsheet methods in RakController"
```

---

### Task 3: Pendaftaran Route `/rak/export` di `public/index.php`

**Files:**
- Modify: `public/index.php:425-435`

**Interfaces:**
- Consumes: HTTP GET `/rak/export`, `$rakController->export()`
- Produces: Pemanggilan `$rakController->export()` saat path `/rak/export` diakses.

- [ ] **Step 1: Daftarkan route `/rak/export` sebelum route `/rak` umum**

Di `public/index.php` pada blok `Route matching - RAK`:
```php
    // Route matching - RAK
    elseif ($path === '/rak/export' && $requestMethod === 'GET') {
        $rakController->export();
    } elseif ($path === '/rak/rekap' || $path === '/rak/rekap/') {
        $rakController->rekap();
    } elseif ($path === '/rak' || $path === '/rak/') {
        $rakController->index();
```

- [ ] **Step 2: Jalankan uji tes sintaks PHP pada `public/index.php`**

Run: `php -l public/index.php`
Expected: `No syntax errors detected in public/index.php`

- [ ] **Step 3: Commit route baru**

```bash
git add public/index.php
git commit -m "feat: register /rak/export route in public/index.php"
```

---

### Task 4: Tambahkan Tombol "Export Excel" pada View `views/rak/index.php`

**Files:**
- Modify: `views/rak/index.php:47-60`

**Interfaces:**
- Consumes: Query string saat ini `$_GET` (`tahun`, `kegiatan_id`, `sub_kegiatan_id`)
- Produces: Tombol `<a href="<?= base_url('rak/export?' . http_build_query($exportParams)) ?>" class="btn btn-success">`

- [ ] **Step 1: Siapkan parameter export dan tambahkan tombol di header halaman RAK**

Pada `views/rak/index.php`, sebelum page-header siapkan link export:
```php
$exportParams = array_filter([
    'tahun'           => $filterTahun,
    'kegiatan_id'     => $filterKegiatan,
    'sub_kegiatan_id' => $filterSubKegiatan,
], fn($v) => $v !== null && $v !== '');
$exportUrl = base_url('rak/export' . (!empty($exportParams) ? '?' . http_build_query($exportParams) : ''));
```

Lalu di bagian tombol action header (baris 55-58):
```html
            <div class="d-flex gap-2">
                <a href="<?= $exportUrl ?>" class="btn btn-success" title="Export RAK ke Excel">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </a>
                <a href="<?= base_url('rak/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Tambah RAK
                </a>
            </div>
```

- [ ] **Step 2: Jalankan uji sintaks PHP pada `views/rak/index.php`**

Run: `php -l views/rak/index.php`
Expected: `No syntax errors detected in views/rak/index.php`

- [ ] **Step 3: Commit tampilan view RAK**

```bash
git add views/rak/index.php
git commit -m "feat: add export excel button in RAK index view"
```

---

### Task 5: Pengujian End-to-End & Verifikasi File Output Excel

**Files:**
- Create: `tests/test_e2e_rak_export.php`

**Interfaces:**
- Consumes: `RakController::buildSpreadsheet` dengan filter spesifik dan tanpa filter.
- Produces: Memverifikasi perhitungan formula dan struktur file Excel tersimpan dengan benar di disk temporary.

- [ ] **Step 1: Buat skrip tes integrasi file export `tests/test_e2e_rak_export.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Controllers/RakController.php';

use App\Models\Rak;
use App\Models\Pagu;
use App\Models\Program;
use App\Models\Kegiatan;
use App\Models\SubKegiatan;
use App\Models\Rekening;
use App\Controllers\RakController;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = Database::getConnection();
$controller = new RakController(
    new Rak($db),
    new Pagu($db),
    new Program($db),
    new Kegiatan($db),
    new SubKegiatan($db),
    new Rekening($db)
);

// 1. Test build spreadsheet dengan filter tahun 2026
$ss2026 = $controller->buildSpreadsheet(2026, null, null);
assert($ss2026->getSheetCount() === 1, 'Harus ada 1 sheet');

// 2. Simpan ke temporary file untuk memastikan writer Xlsx bekerja tanpa error
$tmpFile = sys_get_temp_dir() . '/test_rak_export_' . time() . '.xlsx';
$writer = new Xlsx($ss2026);
$writer->save($tmpFile);

assert(file_exists($tmpFile), 'File temporary excel harus berhasil disimpan');
assert(filesize($tmpFile) > 1000, 'Ukuran file excel harus valid (> 1KB)');

@unlink($tmpFile);

echo "✓ Test E2E RAK Export XLSX berhasil menghasilkan file valid!\n";
```

- [ ] **Step 2: Jalankan tes E2E**

Run: `php tests/test_e2e_rak_export.php`
Expected: `✓ Test E2E RAK Export XLSX berhasil menghasilkan file valid!`

- [ ] **Step 3: Jalankan seluruh suite tes yang relevan di direktori `tests/`**

Run:
```bash
php tests/test_rak_excel_export.php
php tests/test_e2e_rak_export.php
```
Expected: Semua tes lolos.

- [ ] **Step 4: Commit tes E2E**

```bash
git add tests/test_e2e_rak_export.php
git commit -m "test: add e2e test for RAK excel export generation"
```
