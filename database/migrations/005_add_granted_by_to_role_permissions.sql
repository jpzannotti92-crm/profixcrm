-- Migration: Add granted_by to role_permissions
-- Purpose: enable auditing of who granted a permission to a role.
-- This migration is idempotent for MySQL 8.0+ using IF NOT EXISTS.

START TRANSACTION;

-- 1) Add nullable column granted_by
ALTER TABLE `role_permissions`
  ADD COLUMN IF NOT EXISTS `granted_by` INT NULL COMMENT 'User who granted this permission';

-- 2) Add index for faster joins/lookups
ALTER TABLE `role_permissions`
  ADD INDEX IF NOT EXISTS `idx_role_permissions_granted_by` (`granted_by`);

-- 3) Add foreign key to users(id)
-- Note: MySQL does not support IF NOT EXISTS for FOREIGN KEY constraints,
-- so this statement should be executed only once.
-- If the constraint already exists, run: 
--   ALTER TABLE `role_permissions` DROP FOREIGN KEY `fk_role_permissions_granted_by`;
-- before re-applying it.
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_granted_by`
  FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)
  ON DELETE SET NULL
  ON UPDATE CASCADE;

COMMIT;