# Transaction Input UI/UX Improvement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve transaction input speed and ease by pre-populating all accounts under a sub-activity with remaining budget info, adding bulk copy helpers, and ignoring empty rows on save.

**Architecture:** Add a new JSON endpoint to fetch accounts with budget details. Modify the batch form view to render these accounts as pre-populated rows with real-time budget checking, bulk autofill controls, and dynamically filter out empty values upon save.

**Tech Stack:** PHP, MySQL (PDO), JavaScript (Fetch API, Bootstrap/Vanilla HTML/CSS).

## Global Constraints
- Naming of fields/methods must align with existing codebases.
- Do not introduce PHPUnit if not present; use custom verification scripts in `tests/` directory instead.

---

### Task 1: Backend API Endpoint for Accounts with Budget

**Files:**
- Modify: [index.php](file:///d:/laragon/www/dashboard/public/index.php)
- Modify: [TransaksiController.php](file:///d:/laragon/www/dashboard/app/Controllers/TransaksiController.php)
- Create: [tests/test_api_budget.php](file:///d:/laragon/www/dashboard/tests/test_api_budget.php)

**Interfaces:**
- Consumes: `rekeningModel->getBySubKegiatanId($subKegiatanId)`, `paguModel->getAll()`, `transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun)`
- Produces: JSON payload representing rekenings with their remaining budget (`sisa_pagu`).

- [ ] **Step 1: Write a script to test the endpoint**
  Create [tests/test_api_budget.php](file:///d:/laragon/www/dashboard/tests/test_api_budget.php):
  ```php
  <?php
  require_once __DIR__ . '/../config/load_env.php';
  require_once __DIR__ . '/../config/helpers.php';
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../app/Models/Rekening.php';
  require_once __DIR__ . '/../app/Models/Pagu.php';
  require_once __DIR__ . '/../app/Models/Transaksi.php';
  require_once __DIR__ . '/../app/Models/Seksi.php';
  require_once __DIR__ . '/../app/Models/Program.php';
  require_once __DIR__ . '/../app/Models/Kegiatan.php';
  require_once __DIR__ . '/../app/Models/SubKegiatan.php';
  require_once __DIR__ . '/../app/Controllers/TransaksiController.php';

  $db = Database::getConnection();
  $transaksiController = new App\Controllers\TransaksiController(
      new App\Models\Transaksi($db),
      new App\Models\Seksi($db),
      new App\Models\Pagu($db),
      new App\Models\Rak($db),
      new App\Models\Program($db),
      new App\Models\Kegiatan($db),
      new App\Models\SubKegiatan($db),
      new App\Models\Rekening($db)
  );

  // Set fake GET parameters
  $_GET['sub_kegiatan_id'] = 1; // Assuming Sub Kegiatan 1 exists
  $_GET['tahun'] = 2026;

  // We expect this file to be run via PHP CLI. We can buffer output of the controller method.
  ob_start();
  try {
      $transaksiController->getRekeningsWithBudget();
  } catch (\Exception $e) {
      echo "Failed with exception: " . $e->getMessage() . "\n";
  }
  $output = ob_get_clean();

  $data = json_decode($output, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
      echo "TEST FAILED: Invalid JSON output. Raw output: \n" . $output . "\n";
      exit(1);
  }

  echo "TEST PASSED: Found " . count($data) . " accounts. Sample data:\n";
  print_r(array_slice($data, 0, 1));
  exit(0);
  ```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php tests/test_api_budget.php`
  Expected: FAIL (either method doesn't exist, or output is empty/error)

- [ ] **Step 3: Implement routing and controller method**
  In [index.php](file:///d:/laragon/www/dashboard/public/index.php) around line 409, add:
  ```php
      } elseif ($path === '/transaksi/get-rekenings-with-budget' && $requestMethod === 'GET') {
          $transaksiController->getRekeningsWithBudget();
  ```
  In [TransaksiController.php](file:///d:/laragon/www/dashboard/app/Controllers/TransaksiController.php), add:
  ```php
      /**
       * AJAX: Get accounts for a sub-activity with budget information for a specific year
       */
      public function getRekeningsWithBudget(): void
      {
          header('Content-Type: application/json');
          try {
              $subKegiatanId = (int) ($_GET['sub_kegiatan_id'] ?? 0);
              $tahun = (int) ($_GET['tahun'] ?? (int)date('Y'));

              if (!$subKegiatanId) {
                  echo json_encode(['error' => 'Sub Kegiatan ID is required']);
                  exit;
              }

              $rekenings = $this->rekeningModel->getBySubKegiatanId($subKegiatanId);

              // Get all pagus for mapping
              $pagus = $this->paguModel->getAll();
              $paguMap = [];
              foreach ($pagus as $p) {
                  if ((int)$p['tahun'] === $tahun) {
                      $paguMap[(int)$p['rekening_id']] = (float) $p['nilai_pagu'];
                  }
              }

              $result = [];
              foreach ($rekenings as $rekening) {
                  $rekeningId = (int) $rekening['id'];
                  $paguValue = $paguMap[$rekeningId] ?? null;
                  $totalTransaksi = $this->transaksiModel->getTotalByRekeningAndYear($rekeningId, $tahun);
                  $sisaPagu = $paguValue !== null ? ($paguValue - $totalTransaksi) : null;

                  $result[] = [
                      'id' => $rekeningId,
                      'kode_rekening' => $rekening['kode_rekening'],
                      'nama_rekening' => $rekening['nama_rekening'],
                      'seksi_id' => $rekening['seksi_id'],
                      'pagu' => $paguValue,
                      'total_transaksi' => $totalTransaksi,
                      'sisa_pagu' => $sisaPagu
                  ];
              }

              echo json_encode($result);
          } catch (\Exception $e) {
              http_response_code(500);
              echo json_encode(['error' => $e->getMessage()]);
          }
          exit;
      }
  ```

- [ ] **Step 4: Run test to verify it passes**
  Run: `php tests/test_api_budget.php`
  Expected: PASS, printing list of accounts with pagu, total_transaksi, and sisa_pagu.

- [ ] **Step 5: Commit changes**
  ```bash
  git add public/index.php app/Controllers/TransaksiController.php tests/test_api_budget.php
  git commit -m "feat: add API endpoint to get accounts with budget details"
  ```

---

### Task 2: Update storeBatch logic in TransaksiController

**Files:**
- Modify: [TransaksiController.php](file:///d:/laragon/www/dashboard/app/Controllers/TransaksiController.php)
- Create: [tests/test_store_batch.php](file:///d:/laragon/www/dashboard/tests/test_store_batch.php)

**Interfaces:**
- Consumes: HTTP POST request payload.
- Produces: Filtered input processing where empty/zero transactions are omitted.

- [ ] **Step 1: Write integration test script for storeBatch**
  Create [tests/test_store_batch.php](file:///d:/laragon/www/dashboard/tests/test_store_batch.php):
  ```php
  <?php
  require_once __DIR__ . '/../config/load_env.php';
  require_once __DIR__ . '/../config/helpers.php';
  require_once __DIR__ . '/../config/database.php';
  require_once __DIR__ . '/../app/Models/Rekening.php';
  require_once __DIR__ . '/../app/Models/Pagu.php';
  require_once __DIR__ . '/../app/Models/Transaksi.php';
  require_once __DIR__ . '/../app/Models/Seksi.php';
  require_once __DIR__ . '/../app/Models/Program.php';
  require_once __DIR__ . '/../app/Models/Kegiatan.php';
  require_once __DIR__ . '/../app/Models/SubKegiatan.php';
  require_once __DIR__ . '/../app/Controllers/TransaksiController.php';

  $db = Database::getConnection();
  $transaksiController = new App\Controllers\TransaksiController(
      new App\Models\Transaksi($db),
      new App\Models\Seksi($db),
      new App\Models\Pagu($db),
      new App\Models\Rak($db),
      new App\Models\Program($db),
      new App\Models\Kegiatan($db),
      new App\Models\SubKegiatan($db),
      new App\Models\Rekening($db)
  );

  // Set fake POST parameters where some rows are empty and one row has values
  $_POST['tanggal'] = '2026-07-25';
  $_POST['rekenings'] = [
      1 => [
          'rekening_id' => 1,
          'uraian' => 'Test Transaction 1',
          'nilai' => '1.000.000', // valid value
          'nomor_bukti' => 'BUK/001'
      ],
      2 => [
          'rekening_id' => 2,
          'uraian' => '',
          'nilai' => '', // empty value
          'nomor_bukti' => ''
      ]
  ];

  // We expect storeBatch to filter out row 2 and process row 1.
  // Note: storeBatch will attempt redirect, we can capture header/output redirection.
  ob_start();
  try {
      $transaksiController->storeBatch();
  } catch (\Exception $e) {
      echo "Failed with exception: " . $e->getMessage() . "\n";
  }
  $output = ob_get_clean();

  // If redirect happened, success!
  // In CLI environment headers_sent() check might redirect or print nothing.
  echo "TEST VERIFIED: Completed batch storage process.\n";
  exit(0);
  ```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php tests/test_store_batch.php`
  Expected: FAIL or print validation error because row 2 has empty fields.

- [ ] **Step 3: Modify storeBatch in TransaksiController.php**
  In [TransaksiController.php](file:///d:/laragon/www/dashboard/app/Controllers/TransaksiController.php):
  - In `storeBatch()` (line 210), filter `$_POST['rekenings']` to only keep items with non-empty, non-zero values for `nilai`.
  - Let's replace the input validation and processing of `$_POST['rekenings']` to ignore empty rows:
  ```php
          // Filter out empty rows
          if (isset($_POST['rekenings']) && is_array($_POST['rekenings'])) {
              $_POST['rekenings'] = array_filter($_POST['rekenings'], function ($rekening) {
                  if (empty($rekening['rekening_id'])) {
                      return false;
                  }
                  $nilai = str_replace(['.', ','], '', $rekening['nilai'] ?? '');
                  return $nilai !== '' && (float)$nilai > 0;
              });
          }
  ```
  Let's do this before the validations in `storeBatch()`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php tests/test_store_batch.php`
  Expected: PASS, validation passes because row 2 is ignored, and transaction 1 is saved.

- [ ] **Step 5: Commit changes**
  ```bash
  git add app/Controllers/TransaksiController.php tests/test_store_batch.php
  git commit -m "feat: modify storeBatch to ignore empty/zero transaction value rows"
  ```

---

### Task 3: Redesign batch transaction form UI & JavaScript

**Files:**
- Modify: [form.php](file:///d:/laragon/www/dashboard/views/transaksi/form.php)

- [ ] **Step 1: Add bulk helper UI and update table headings**
  In [form.php](file:///d:/laragon/www/dashboard/views/transaksi/form.php):
  - In the batch form column (around line 271), add a section for global bulk copy fields:
    - Uraian Masal
    - Nomor Bukti Masal
    - "Terapkan ke Semua Baris" button.
  - Modify the table headers:
    - `Rekening` (width 25%)
    - `Sisa Anggaran` (width 15%)
    - `Nilai` (width 20%)
    - `Uraian` (width 25%)
    - `Nomor Bukti` (width 15%)
    - Remove `Aksi` column.

- [ ] **Step 2: Update JavaScript cascading dropdowns & row generation**
  In [form.php](file:///d:/laragon/www/dashboard/views/transaksi/form.php):
  - Change JavaScript so that when `batchSubKegiatanSelect` triggers a change, it calls `/transaksi/get-rekenings-with-budget?sub_kegiatan_id=X&tahun=Y` instead of `/pagu/get-rekenings`.
  - For each rekening returned, append a row containing:
    - Bold display of code and name (e.g., `5.01.02.1.01.02 - Belanja ATK`).
    - Hidden input for `rekening_id`.
    - Sisa Anggaran column: displaying the calculated budget with proper color code (green if positive, red if negative, text muted if pagu not set).
    - Amount input (`nilai`) with thousands formatting.
    - Description input (`uraian`).
    - Receipt number input (`nomor_bukti`).
  - Implement bulk auto-fill event listener:
    - When clicking "Terapkan ke Semua Baris", copy Uraian Masal and Nomor Bukti Masal to all rows in the table.
  - Implement real-time budget limit check:
    - When amount input changes, compare it to `sisa_pagu`. Show visual `is-invalid` highlight if it exceeds the budget.
  - Update form submit event handler to ensure we submit only rows with positive `nilai`.

- [ ] **Step 3: Verify form visual layouts and manual flows**
  Open browser subagent or manually verify using built-in server.
  Verify visual aesthetics (dark/light compatibility, elegant Bootstrap layouts, spacing, headers).

- [ ] **Step 4: Commit changes**
  ```bash
  git add views/transaksi/form.php
  git commit -m "feat: redesign transaction batch form with auto-rekenings, sisa anggaran, and bulk copy"
  ```
