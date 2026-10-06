-- =========================================================
-- Migración v2 - SkillSwap
-- Propuesta libre + horas como tiempo real (0.5h), sesiones, extensiones
-- =========================================================
USE skillswap;

-- Saldos y movimientos en decimales (media hora)
ALTER TABLE users
  MODIFY hours_balance DECIMAL(6,1) NOT NULL DEFAULT 10;

ALTER TABLE hours_log
  MODIFY amount DECIMAL(6,1) NOT NULL,
  MODIFY balance_after DECIMAL(6,1) NOT NULL;

-- Intercambios: propuesta libre (direcciones opcionales), horas decimales,
-- sesión agendada y soporte de extensión con confirmación doble
ALTER TABLE exchanges
  DROP CHECK chk_exchange_hours;

ALTER TABLE exchanges
  MODIFY skill_offered_id INT NULL,
  MODIFY proposer_hours DECIMAL(4,1) NOT NULL DEFAULT 0,
  MODIFY acceptor_hours DECIMAL(4,1) NOT NULL DEFAULT 0,
  ADD COLUMN scheduled_at DATETIME NULL AFTER completed_at,
  ADD COLUMN extend_requested_by INT NULL AFTER scheduled_at,
  ADD COLUMN extend_hours DECIMAL(4,1) NULL AFTER extend_requested_by,
  ADD COLUMN extend_direction ENUM('proposer','acceptor') NULL AFTER extend_hours,
  ADD CONSTRAINT fk_extend_requested_by FOREIGN KEY (extend_requested_by) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT chk_exchange_hours CHECK (
      proposer_hours BETWEEN 0 AND 24
      AND acceptor_hours BETWEEN 0 AND 24
      AND (proposer_hours + acceptor_hours) BETWEEN 0.5 AND 48
      AND (proposer_hours = 0) = (skill_offered_id IS NULL)
      AND (acceptor_hours = 0) = (skill_requested_id IS NULL)
  );