-- Run once on production (api.invoicemate.in DB) if addExpense fails with "Unknown column 'expense_date'".
-- Skip if the column already exists.

ALTER TABLE `expenses` ADD COLUMN `expense_date` DATE NULL;
UPDATE `expenses` SET `expense_date` = DATE(`created_at`) WHERE `expense_date` IS NULL;
