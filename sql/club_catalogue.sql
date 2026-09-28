-- Run once to link agenda selections to the active club catalogue.
ALTER TABLE meeting_clubs
    ADD COLUMN club_id INT UNSIGNED NULL AFTER meeting_id,
    ADD COLUMN club_area VARCHAR(30) NOT NULL DEFAULT '' AFTER club_name;
