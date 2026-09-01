# Bulk Delete Transaksi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan fitur hapus banyak transaksi (bulk delete) pada modul transaksi untuk user admin dengan seleksi checkbox, toolbar aksi, konfirmasi modal, dan backend batch deletion.

**Architecture:** Model `Transaksi` menangani penghapusan masal dan unlinking referensi SPJ dalam database transaction; Controller `TransaksiController` memvalidasi array ID transaksi dari HTTP POST dan mengarahkan kembali dengan flash message; View `views/transaksi/index.php` menyediakan antarmuka seleksi checkbox interaktif, counter terpilih, modal konfirmasi, dan submission form.

**Tech Stack:** PHP 8+, MySQL/MariaDB PDO, Bootstrap 5, Vanilla JavaScript.

## Global Constraints
- Naming convention: Method `deleteBatch` pada Model dan Controller.
- Route: `POST /transaksi/delete-batch`.
- CSRF / Form payload: Parameter array `ids[]` berisikan ID transaksi integer positif.
- Foreign Key Integrity: Putuskan relasi `transaksi_id` di `rincian_biaya_perjalanan_dinas` jika ada (`SET transaksi_id = NULL`).
- Pesan Flash: Sukses -> `"Berhasil menghapus X transaksi"`, Error/Kosong -> `"Tidak ada transaksi yang dipilih untuk dihapus"` atau detail pesan error.

---

### Task 1: Add `deleteBatch` method in `Transaksi` Model

**Files:**
- Modify: `app/Models/Transaksi.php`
- Test: `tests/test_transaksi_delete_batch.php`

**Interfaces:**
- Produces: `public function deleteBatch(array $ids): int`

- [ ] **Step 1: Write integration test for `Transaksi::deleteBatch`**

Create `tests/test_transaksi_delete_batch.php`:
```php
<?php

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

$db = Database::getConnection();
$transaksiModel = new \App\Models\Transaksi($db);

echo "Starting test_transaksi_delete_batch...\n";

// 1. Dapatkan referensi rekening & seksi yang ada untuk dummy data
$stmt = $db->query("SELECT id, seksi_id FROM rekening WHERE seksi_id IS NOT NULL LIMIT 1");
$rek = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rek) {
    echo "SKIP: Tidak ada rekening dengan seksi_id untuk pengujian.\n";
    exit(0);
}

// 2. Insert dummy transactions
$ids = [];
for ($i = 1; $i <= 3; $i++) {
    $stmt = $db->prepare("
        INSERT INTO transaksi (tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti, status)
        VALUES (CURDATE(), :seksi_id, :rekening_id, :uraian, 50000, :no_bukti, 'diajukan')
    ");
    $uraian = "Dummy Transaksi DeleteBatch {$i} - " . time();
    $noBukti = "TEST-DEL-{$i}-" . time();
    $stmt->execute([
        ':seksi_id' => $rek['seksi_id'],
        ':rekening_id' => $rek['id'],
        ':uraian' => $uraian,
        ':no_bukti' => $noBukti,
    ]);
    $ids[] = (int) $db->lastInsertId();
}

echo "Created 3 dummy transactions: " . implode(', ', $ids) . "\n";

// 3. Test deleteBatch dengan array kosong
$deletedEmpty = $transaksiModel->deleteBatch([]);
if ($deletedEmpty !== 0) {
    echo "FAIL: deleteBatch([]) should return 0, got {$deletedEmpty}\n";
    exit(1);
}

// 4. Test deleteBatch dengan IDs dummy
$deletedCount = $transaksiModel->deleteBatch($ids);
if ($deletedCount !== 3) {
    echo "FAIL: Expected 3 deleted rows, got {$deletedCount}\n";
    exit(1);
}

// 5. Verify records no longer exist in database
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$checkStmt = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE id IN ($placeholders)");
$checkStmt->execute($ids);
$remaining = (int) $checkStmt->fetchColumn();

if ($remaining !== 0) {
    echo "FAIL: Expected 0 remaining records, got {$remaining}\n";
    exit(1);
}

echo "TEST PASSED: Transaksi::deleteBatch works correctly.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_transaksi_delete_batch.php`
Expected: Call to undefined method `App\Models\Transaksi::deleteBatch()` or error.

- [ ] **Step 3: Implement `deleteBatch` in `app/Models/Transaksi.php`**

Add method to `app/Models/Transaksi.php`:
```php
    /**
     * Delete multiple transactions by IDs in a single database transaction.
     * Also unlinks any associated rincian_biaya_perjalanan_dinas.
     * 
     * @param int[] $ids
     * @return int Number of deleted transactions
     */
    public function deleteBatch(array $ids): int
    {
        $validIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($validIds)) {
            return 0;
        }

        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));

            // Unlink rincian_biaya_perjalanan_dinas jika ada
            try {
                $unlinkStmt = $this->db->prepare("UPDATE rincian_biaya_perjalanan_dinas SET transaksi_id = NULL WHERE transaksi_id IN ($placeholders)");
                $unlinkStmt->execute($validIds);
            } catch (\Exception $e) {
                // Kolom/tabel mungkin opsional jika belum dimigrasi
                error_log("Notice unlinking rincian_biaya_perjalanan_dinas on deleteBatch: " . $e->getMessage());
            }

            // Hapus data transaksi
            $stmt = $this->db->prepare("DELETE FROM transaksi WHERE id IN ($placeholders)");
            $stmt->execute($validIds);
            $deletedCount = $stmt->rowCount();

            $this->db->commit();
            return $deletedCount;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log('Error in Transaksi::deleteBatch: ' . $e->getMessage());
            throw new \RuntimeException('Gagal menghapus batch transaksi: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_transaksi_delete_batch.php`
Expected: `TEST PASSED: Transaksi::deleteBatch works correctly.`

- [ ] **Step 5: Commit**

```bash
git add app/Models/Transaksi.php tests/test_transaksi_delete_batch.php
git commit -m "feat: add deleteBatch method in Transaksi model with test"
```

---

### Task 2: Implement Controller and Route for Bulk Delete

**Files:**
- Modify: `app/Controllers/TransaksiController.php`
- Modify: `public/index.php`
- Test: `tests/test_transaksi_controller_delete_batch.php`

**Interfaces:**
- Consumes: `Transaksi::deleteBatch(array $ids)`
- Produces: `TransaksiController::deleteBatch(): void`, Route `POST /transaksi/delete-batch`

- [ ] **Step 1: Write test for `TransaksiController::deleteBatch`**

Create `tests/test_transaksi_controller_delete_batch.php`:
```php
<?php

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Seksi.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Controllers/TransaksiController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$transaksiModel = new \App\Models\Transaksi($db);
$seksiModel = new \App\Models\Seksi($db);
$paguModel = new \App\Models\Pagu($db);
$rakModel = new \App\Models\Rak($db);
$programModel = new \App\Models\Program($db);
$kegiatanModel = new \App\Models\Kegiatan($db);
$subKegiatanModel = new \App\Models\SubKegiatan($db);
$rekeningModel = new \App\Models\Rekening($db);

$controller = new \App\Controllers\TransaksiController(
    $transaksiModel,
    $seksiModel,
    $paguModel,
    $rakModel,
    $programModel,
    $kegiatanModel,
    $subKegiatanModel,
    $rekeningModel
);

echo "Starting test_transaksi_controller_delete_batch...\n";

// 1. Create dummy transactions
$stmt = $db->query("SELECT id, seksi_id FROM rekening WHERE seksi_id IS NOT NULL LIMIT 1");
$rek = $stmt->fetch(PDO::FETCH_ASSOC);

$ids = [];
for ($i = 1; $i <= 2; $i++) {
    $stmt = $db->prepare("
        INSERT INTO transaksi (tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti, status)
        VALUES (CURDATE(), :seksi_id, :rekening_id, :uraian, 75000, :no_bukti, 'diverifikasi')
    ");
    $stmt->execute([
        ':seksi_id' => $rek['seksi_id'],
        ':rekening_id' => $rek['id'],
        ':uraian' => "Dummy Controller DeleteBatch {$i} - " . time(),
        ':no_bukti' => "CTRL-DEL-{$i}-" . time(),
    ]);
    $ids[] = (int) $db->lastInsertId();
}

// 2. Mock POST data
$_POST['ids'] = $ids;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Execute deleteBatch with output buffering to suppress redirect headers in CLI
ob_start();
try {
    $controller->deleteBatch();
} catch (\Throwable $e) {
    // redirectWithMessage might exit or set header
}
ob_end_clean();

// 3. Verify session flash message
if (empty($_SESSION['flash_message']) || $_SESSION['flash_type'] !== 'success') {
    echo "FAIL: Flash message not set properly. Message: " . ($_SESSION['flash_message'] ?? 'null') . "\n";
    exit(1);
}

// 4. Verify rows deleted from database
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$checkStmt = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE id IN ($placeholders)");
$checkStmt->execute($ids);
$remaining = (int) $checkStmt->fetchColumn();

if ($remaining !== 0) {
    echo "FAIL: Expected 0 remaining rows, got {$remaining}\n";
    exit(1);
}

echo "TEST PASSED: TransaksiController::deleteBatch executed successfully.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_transaksi_controller_delete_batch.php`
Expected: Call to undefined method `App\Controllers\TransaksiController::deleteBatch()`

- [ ] **Step 3: Implement `deleteBatch` in `app/Controllers/TransaksiController.php` and route in `public/index.php`**

In `app/Controllers/TransaksiController.php`:
```php
    /**
     * Delete multiple transactions
     */
    public function deleteBatch(): void
    {
        try {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $this->redirectWithMessage(base_url('transaksi'), 'error', 'Tidak ada transaksi yang dipilih untuk dihapus.');
                return;
            }

            $validIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($validIds)) {
                $this->redirectWithMessage(base_url('transaksi'), 'error', 'ID transaksi yang dipilih tidak valid.');
                return;
            }

            $deletedCount = $this->transaksiModel->deleteBatch($validIds);
            
            $redirectUrl = $_POST['redirect_to'] ?? base_url('transaksi');
            $this->redirectWithMessage($redirectUrl, 'success', "Berhasil menghapus {$deletedCount} transaksi.");
        } catch (\Exception $e) {
            $redirectUrl = $_POST['redirect_to'] ?? base_url('transaksi');
            $this->redirectWithMessage($redirectUrl, 'error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
```

In `public/index.php` (around line 450):
```php
    } elseif ($path === '/transaksi/delete-batch' && $requestMethod === 'POST') {
        $transaksiController->deleteBatch();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_transaksi_controller_delete_batch.php`
Expected: `TEST PASSED: TransaksiController::deleteBatch executed successfully.`

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/TransaksiController.php public/index.php tests/test_transaksi_controller_delete_batch.php
git commit -m "feat: add deleteBatch action in TransaksiController and register route"
```

---

### Task 3: Update `views/transaksi/index.php` with Checkbox Selection, Bulk Action Bar, and Confirmation Modal

**Files:**
- Modify: `views/transaksi/index.php`

- [ ] **Step 1: Add Checkbox Column and Header in `views/transaksi/index.php`**
- In `<thead><tr>`:
  - Tambahkan kolom checkbox di paling kiri:
    ```html
    <th width="3%" class="text-center">
        <input type="checkbox" class="form-check-input" id="check-all-trx" title="Pilih Semua di Halaman Ini">
    </th>
    ```
- In `<tbody><tr>`:
  - Tambahkan sel checkbox di setiap baris:
    ```html
    <td class="text-center">
        <input type="checkbox" class="form-check-input row-trx-checkbox" value="<?= $transaksi['id'] ?>">
    </td>
    ```
- Sesuaikan `colspan` di `<tfoot>`: dari `colspan="6"` menjadi `colspan="7"`.

- [ ] **Step 2: Add Bulk Action Bar**
- Di atas card / di samping button Tambah Transaksi & Unduh BKU:
  - Sediakan container aksi massal:
    ```html
    <div id="bulk-action-bar" class="d-none alert alert-light border d-flex align-items-center justify-content-between p-2 mb-3">
        <div class="d-flex align-items-center">
            <i class="bi bi-check2-square text-primary fs-5 me-2"></i>
            <span><strong id="selected-count">0</strong> transaksi terpilih</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-uncheck-all">
                <i class="bi bi-x me-1"></i>Batal Pilih
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-bulk-delete">
                <i class="bi bi-trash me-1"></i>Hapus Terpilih
            </button>
        </div>
    </div>
    ```

- [ ] **Step 3: Add Modal Konfirmasi Bulk Delete & Form**
- Tambahkan Modal konfirmasi di bagian bawah view:
  ```html
  <div class="modal fade" id="modalBulkDelete" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
          <form class="modal-content" id="formBulkDelete" method="POST" action="<?= base_url('transaksi/delete-batch') ?>">
              <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? base_url('transaksi')) ?>">
              <div class="modal-header">
                  <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Konfirmasi Hapus Banyak Transaksi</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  <p>Apakah Anda yakin ingin menghapus <strong id="modal-delete-count">0</strong> transaksi yang dipilih?</p>
                  <p class="text-danger small mb-0"><i class="bi bi-info-circle me-1"></i>Tindakan ini tidak dapat dibatalkan.</p>
                  <div id="bulk-delete-inputs"></div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Hapus Sekarang</button>
              </div>
          </form>
      </div>
  </div>
  ```

- [ ] **Step 4: Add JavaScript Logic in `views/transaksi/index.php`**
- Implementasikan logic event listener:
  ```javascript
  (function () {
      const checkAll = document.getElementById('check-all-trx');
      const rowCheckboxes = document.querySelectorAll('.row-trx-checkbox');
      const bulkBar = document.getElementById('bulk-action-bar');
      const selectedCountEl = document.getElementById('selected-count');
      const modalDeleteCountEl = document.getElementById('modal-delete-count');
      const btnUncheck = document.getElementById('btn-uncheck-all');
      const btnBulkDelete = document.getElementById('btn-bulk-delete');
      const modalBulkDelete = new bootstrap.Modal(document.getElementById('modalBulkDelete'));
      const inputsContainer = document.getElementById('bulk-delete-inputs');

      function updateSelectionState() {
          const checkedBoxes = document.querySelectorAll('.row-trx-checkbox:checked');
          const count = checkedBoxes.length;

          if (selectedCountEl) selectedCountEl.textContent = count;
          if (modalDeleteCountEl) modalDeleteCountEl.textContent = count;

          if (bulkBar) {
              if (count > 0) {
                  bulkBar.classList.remove('d-none');
              } else {
                  bulkBar.classList.add('d-none');
              }
          }

          if (checkAll && rowCheckboxes.length > 0) {
              checkAll.checked = count === rowCheckboxes.length;
              checkAll.indeterminate = count > 0 && count < rowCheckboxes.length;
          }
      }

      if (checkAll) {
          checkAll.addEventListener('change', function () {
              rowCheckboxes.forEach(cb => {
                  cb.checked = checkAll.checked;
              });
              updateSelectionState();
          });
      }

      rowCheckboxes.forEach(cb => {
          cb.addEventListener('change', updateSelectionState);
      });

      if (btnUncheck) {
          btnUncheck.addEventListener('click', function () {
              if (checkAll) checkAll.checked = false;
              rowCheckboxes.forEach(cb => cb.checked = false);
              updateSelectionState();
          });
      }

      if (btnBulkDelete) {
          btnBulkDelete.addEventListener('click', function () {
              const checkedBoxes = document.querySelectorAll('.row-trx-checkbox:checked');
              if (checkedBoxes.length === 0) return;

              if (inputsContainer) {
                  inputsContainer.innerHTML = '';
                  checkedBoxes.forEach(cb => {
                      const hiddenInput = document.createElement('input');
                      hiddenInput.type = 'hidden';
                      hiddenInput.name = 'ids[]';
                      hiddenInput.value = cb.value;
                      inputsContainer.appendChild(hiddenInput);
                  });
              }

              modalBulkDelete.show();
          });
      }
  })();
  ```

- [ ] **Step 5: Test & Commit**
```bash
git add views/transaksi/index.php
git commit -m "feat: add bulk delete selection UI, action bar, and modal in transaksi index"
```

---

### Task 4: Complete Verification & Cleanup

**Files:**
- Clean up test files if necessary or keep in `tests/`
- Run all test suites

- [ ] **Step 1: Run integration test suites**
Run:
`php tests/test_transaksi_delete_batch.php`
`php tests/test_transaksi_controller_delete_batch.php`
`php tests/test_store_batch.php`

- [ ] **Step 2: Commit any final updates**
```bash
git status
```
