-- =====================================================
-- MIGRACIÓN: Simplificar tabla lead_activities
-- Descripción: Eliminar columnas innecesarias para simplificar la tabla
-- =====================================================

-- Eliminar columnas innecesarias de la tabla lead_activities
ALTER TABLE `lead_activities` 
DROP COLUMN IF EXISTS `subject`,
DROP COLUMN IF EXISTS `status`,
DROP COLUMN IF EXISTS `scheduled_at`,
DROP COLUMN IF EXISTS `completed_at`,
DROP COLUMN IF EXISTS `duration_minutes`,
DROP COLUMN IF EXISTS `outcome`,
DROP COLUMN IF EXISTS `next_action`,
DROP COLUMN IF EXISTS `attachments`;

-- Modificar el tipo para que solo sea 'note' ya que solo usaremos comentarios
ALTER TABLE `lead_activities` 
MODIFY COLUMN `type` ENUM('note') NOT NULL DEFAULT 'note';

-- Eliminar índices que ya no son necesarios
DROP INDEX IF EXISTS `idx_status` ON `lead_activities`;
DROP INDEX IF EXISTS `idx_scheduled_at` ON `lead_activities`;

-- La tabla simplificada tendrá solo:
-- id, lead_id, user_id, type (solo 'note'), description, created_at, updated_at