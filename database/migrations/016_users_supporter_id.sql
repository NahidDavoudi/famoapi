-- Migration 016: store the assigned supporter on the student's user row.
ALTER TABLE users ADD COLUMN IF NOT EXISTS supporter_id INT(11) NULL;
