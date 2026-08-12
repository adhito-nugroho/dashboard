-- Rollback: Remove seksi_id column from program table
-- Date: 2026-01-12

-- Step 1: Drop foreign key constraint
ALTER TABLE `program`
DROP FOREIGN KEY `fk_program_seksi`;

-- Step 2: Drop index
ALTER TABLE `program`
DROP INDEX `idx_seksi_id`;

-- Step 3: Drop seksi_id column
ALTER TABLE `program`
DROP COLUMN `seksi_id`;

-- Note: seksi_id should be in sub_kegiatan table, not program table
