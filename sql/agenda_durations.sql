-- Safe upgrade for existing installations; saved agendas are preserved.
CREATE TABLE IF NOT EXISTS agenda_durations (
    meeting_id INT UNSIGNED NOT NULL,
    item_key VARCHAR(60) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    PRIMARY KEY (meeting_id, item_key),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);
