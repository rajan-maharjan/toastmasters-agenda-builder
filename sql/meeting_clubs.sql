CREATE TABLE IF NOT EXISTS meeting_clubs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    club_name VARCHAR(255) NOT NULL,
    meeting_number VARCHAR(50) NOT NULL DEFAULT '',
    officers_json TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);
ALTER TABLE meetings MODIFY meeting_number TEXT;
