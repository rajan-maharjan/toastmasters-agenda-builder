-- Run once on existing installations before saving Word of the Day fields.
USE toasmaster_agenda;
ALTER TABLE meetings
    ADD COLUMN wod_word TEXT NULL,
    ADD COLUMN wod_meaning TEXT NULL,
    ADD COLUMN wod_synonyms TEXT NULL,
    ADD COLUMN wod_example TEXT NULL;
