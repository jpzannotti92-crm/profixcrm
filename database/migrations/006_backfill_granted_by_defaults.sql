-- Migration: Backfill granted_by defaults for existing role_permissions rows
-- Strategy: set granted_by = 1 (system admin) where null, if user id 1 exists.

START TRANSACTION;

-- Ensure user id 1 exists before update
SET @admin_exists := (SELECT COUNT(*) FROM `users` WHERE `id` = 1);

-- Backfill only if admin exists
UPDATE `role_permissions`
SET `granted_by` = 1
WHERE `granted_by` IS NULL AND @admin_exists = 1;

COMMIT;