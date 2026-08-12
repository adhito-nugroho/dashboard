-- Migration: Add seksi_id column to program table
-- Date: 2026-01-12

-- Step 1: Add seksi_id column to program table
ALTER TABLE `program` 
ADD COLUMN `seksi_id` INT(11) NULL AFTER `tahun`,
ADD INDEX `idx_seksi_id` (`seksi_id`);

-- Step 2: Add foreign key constraint (optional, for data integrity)
ALTER TABLE `program`
ADD CONSTRAINT `fk_program_seksi`
FOREIGN KEY (`seksi_id`) REFERENCES `seksi`(`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Step 3: Update existing data (if needed)
-- You can manually set seksi_id for existing programs
-- Example:
-- UPDATE program SET seksi_id = 1 WHERE id IN (1, 2, 3);
-- UPDATE program SET seksi_id = 2 WHERE id IN (4, 5, 6);

-- Note: After running this migration, you need to update existing program records
-- to assign them to appropriate seksi
