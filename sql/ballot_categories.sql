-- Run once on existing installations. NULL means all ballot categories selected.
USE toasmaster_agenda;
ALTER TABLE meetings ADD COLUMN ballot_categories_json TEXT NULL;
