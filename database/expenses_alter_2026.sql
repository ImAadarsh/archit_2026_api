-- Expenses: new columns + optional type (run once on MySQL; skip lines that error if column exists).

ALTER TABLE `expenses` ADD COLUMN `paid_to` VARCHAR(255) NULL;
ALTER TABLE `expenses` ADD COLUMN `gst_number` VARCHAR(32) NULL;
ALTER TABLE `expenses` ADD COLUMN `taxable_amount` DECIMAL(15, 2) NULL DEFAULT NULL;
ALTER TABLE `expenses` ADD COLUMN `gst_amount` DECIMAL(15, 2) NULL DEFAULT NULL;

-- Monthly/Adhoc no longer used in the app; new rows may leave type NULL.
ALTER TABLE `expenses` MODIFY COLUMN `type` TINYINT NULL DEFAULT NULL;

-- `amount` = line total (taxable + GST) for totals and old clients.

-- User-visible expense date (filters/reports use COALESCE(expense_date, created_at) for legacy rows).
ALTER TABLE `expenses` ADD COLUMN `expense_date` DATE NULL;
UPDATE `expenses` SET `expense_date` = DATE(`created_at`) WHERE `expense_date` IS NULL;
