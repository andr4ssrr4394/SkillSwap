-- Corrección v3: skill_requested_id también puede ser NULL (intercambio "solo enseñar")
-- Nota MySQL 8: una columna usada en un CHECK no puede tener FK con acción de referencia
-- (SET NULL/CASCADE); por eso la FK de skill_requested_id se define con RESTRICT/NO ACTION.
USE skillswap;

ALTER TABLE exchanges
  MODIFY skill_requested_id INT NULL,
  ADD CONSTRAINT fk_exchange_requested FOREIGN KEY (skill_requested_id) REFERENCES skills(id) ON DELETE RESTRICT;