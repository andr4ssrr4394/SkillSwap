-- Migración "combinado": incorpora al sistema actual los objetos de esquema que la
-- versión SkillSwap-main usaba en su código pero noIncluded en su dump.
-- Ejecutar UNA sola vez sobre la base ya existente:
--   mysql -u root -p skillswap < migrate_combinado.sql
--
-- Nota de diseño: no se agrega `meeting_date`; se reutiliza `scheduled_at` del
-- sistema actual como fecha de la sesión para evitar duplicar el mismo dato.
USE skillswap;

-- ---------------------------------------------------------------------------
-- 1. Perfil extendido (bio, GitHub, LinkedIn)
-- ---------------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN bio TEXT NULL AFTER semester,
  ADD COLUMN github_url VARCHAR(255) NULL AFTER bio,
  ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER github_url;

-- ---------------------------------------------------------------------------
-- 2. Modalidad de la sesión (virtual / presencial)
--    - meeting_type    : modalidad elegida al proponer
--    - meeting_location: punto de encuentro (solo presencial)
--    - meeting_link    : enlace de videollamada (solo virtual)
-- ---------------------------------------------------------------------------
ALTER TABLE exchanges
  ADD COLUMN meeting_type ENUM('virtual','presencial') NOT NULL DEFAULT 'virtual' AFTER scheduled_at,
  ADD COLUMN meeting_location VARCHAR(255) NULL AFTER scheduled_at,
  ADD COLUMN meeting_link VARCHAR(255) NULL AFTER meeting_location;

-- ---------------------------------------------------------------------------
-- 3. Notificaciones en vivo (campana del encabezado)
-- ---------------------------------------------------------------------------
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'general',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user_feed (user_id, is_read, created_at),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;