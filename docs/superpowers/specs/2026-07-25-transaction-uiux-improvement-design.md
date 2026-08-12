# Design Spec: Transaction Input UI/UX Improvement

This spec outlines the design and technical changes for improving the transaction input UI/UX in the budget monitoring application. It addresses the issue of tedious manual row additions by auto-populating all accounts under a sub-activity and displaying their remaining budgets.

## Goal Description

Improve the speed and ease of transaction input by:
- Pre-populating the transaction table with all accounts under the selected Sub Kegiatan (Sub Activity).
- Showing the remaining budget (sisa anggaran) for each account directly in the table.
- Providing a bulk copy helper ("Uraian Masal" and "Nomor Bukti Masal") to quickly copy description and receipt numbers to all rows.
- Automatically filtering and saving only the rows with a transaction value (`nilai`) greater than zero, ignoring empty rows.

---

## Proposed Changes

### 1. Routing Config
#### [MODIFY] [index.php](file:///d:/laragon/www/dashboard/public/index.php)
- Add a new GET route: `/transaksi/get-rekenings-with-budget` mapped to `TransaksiController->getRekeningsWithBudget()`.

---

### 2. Transaction Controller
#### [MODIFY] [TransaksiController.php](file:///d:/laragon/www/dashboard/app/Controllers/TransaksiController.php)
- Add `getRekeningsWithBudget()`:
  - Accepts `sub_kegiatan_id` and `tahun`.
  - Fetches accounts using `rekeningModel->getBySubKegiatanId()`.
  - For each account, fetches its budget value (`nilai_pagu` from `paguModel`) and total transactions (`total_transaksi` from `transaksiModel`) for the given year.
  - Calculates the remaining budget `sisa_pagu = pagu - total_transaksi`.
  - Returns the list of accounts with their budget details as JSON.
- Modify `storeBatch()`:
  - Filter out rows in `$_POST['rekenings']` where `nilai` is empty, zero, or not provided.
  - Only validate and save rows that have a transaction value (`nilai > 0`).
  - If no rows have a transaction value, return a validation error indicating that at least one transaction value must be entered.

---

### 3. Transaction Form View
#### [MODIFY] [form.php](file:///d:/laragon/www/dashboard/views/transaksi/form.php)
- **Bulk Helper UI**:
  - Add two global fields: "Uraian Masal" (Bulk Description) and "Nomor Bukti Masal" (Bulk Receipt Number).
  - Add a button "Terapkan ke Semua Baris" (Apply to All Rows) to copy these values to all populated rows.
- **Table Restructuring**:
  - Redesign the table headers: `Rekening` (Account), `Sisa Anggaran` (Remaining Budget), `Nilai` (Amount), `Uraian` (Description), and `Nomor Bukti` (Receipt Number).
  - Remove the "Aksi" (Trash/Delete) column since rows are fixed for the selected sub-activity.
  - Remove the "Tambah Rekening" button.
- **JavaScript Enhancements**:
  - Fetch accounts using `/transaksi/get-rekenings-with-budget` when Sub Kegiatan or Date changes.
  - Render each account as a table row, displaying the name, code, and color-coded remaining budget.
  - Handle formatting of input amounts (thousands separator).
  - Add real-time visual indicator (`is-invalid`) if the entered amount exceeds the remaining budget.
  - Prior to submission, clean the form data to only submit rows with values (`nilai > 0`).

---

## Verification Plan

### Manual Verification
1. Navigate to `/transaksi/create`.
2. Choose a Program, Kegiatan, and Sub Kegiatan.
3. Verify that the table automatically fills with all accounts belonging to the chosen Sub Kegiatan.
4. Verify that each row displays its current remaining budget.
5. Fill in the "Uraian Masal" and "Nomor Bukti Masal" and click "Terapkan ke Semua Baris". Verify all row inputs are updated.
6. Enter a value higher than the remaining budget for one of the accounts, verify that it highlights as invalid.
7. Enter valid amounts for 2 accounts, leave the others empty/blank, and submit.
8. Verify that only those 2 transactions are recorded in the database, and no errors occur.
