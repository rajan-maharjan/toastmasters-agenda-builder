-- Run once for existing installations.
ALTER TABLE tiab_meetings ADD COLUMN agenda_order VARCHAR(2) NOT NULL DEFAULT 'TT';
