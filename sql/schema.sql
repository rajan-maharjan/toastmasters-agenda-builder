CREATE DATABASE IF NOT EXISTS toasmaster_agenda;
USE toasmaster_agenda;

CREATE TABLE IF NOT EXISTS tiab_meetings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_number TEXT,
    district VARCHAR(100) DEFAULT 'District 41',
    division VARCHAR(100) DEFAULT 'Division C',
    area VARCHAR(100) DEFAULT 'Area C1',
    theme VARCHAR(500),
    meeting_date DATE,
    start_time TIME DEFAULT '18:00:00',
    end_time TIME DEFAULT '19:30:00',
    timezone VARCHAR(20) DEFAULT 'NPT',
    mission TEXT,
    quote_text TEXT,
    quote_author VARCHAR(255),
    venue_details TEXT,
    wod_word TEXT,
    wod_meaning TEXT,
    wod_synonyms TEXT,
    wod_example TEXT,
    ballot_categories_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tiab_officers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED,
    officer_name VARCHAR(255),
    role_type ENUM('regular','sergeant_at_arms','immediate_past_president') DEFAULT 'regular',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tiab_functional_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED,
    role_key VARCHAR(50),
    person_name VARCHAR(255),
    UNIQUE KEY unique_meeting_role (meeting_id, role_key),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tiab_tt_speakers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED,
    speaker_name VARCHAR(255) DEFAULT 'given during the meeting',
    duration VARCHAR(50) DEFAULT '1-2 mins',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tiab_prepared_speakers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED,
    speaker_name VARCHAR(255),
    topic VARCHAR(500),
    level VARCHAR(100),
    pathways VARCHAR(100),
    duration VARCHAR(50) DEFAULT '5 - 7 mins',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tiab_evaluators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED,
    evaluator_name VARCHAR(255),
    speaker_name VARCHAR(255) COMMENT 'denormalized',
    duration VARCHAR(50) DEFAULT '2-3 mins',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tiab_app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS tiab_agenda_durations (
    meeting_id INT UNSIGNED NOT NULL,
    item_key VARCHAR(60) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    PRIMARY KEY (meeting_id, item_key),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);

INSERT INTO tiab_app_settings (setting_key, setting_value) VALUES
('default_district', 'District 41'),
('default_division', 'Division C'),
('default_area', 'Area C1'),
('default_timezone', 'NPT'),
('default_start_time', '18:00'),
('default_end_time', '19:30'),
('default_mission', 'We provide a supportive and positive learning experience in which members are empowered to develop communication and leadership skills, resulting in greater self-confidence and personal growth'),
('default_quote', '\"Knowing is not enough; we must apply. Willing is not enough; we must do.\"'),
('default_quote_author', 'Johann Wolfgang von Goeth'),
('default_venue', 'Every Thursday @ 6:15 pm\nOnwards in 7th Floor, Meeting Hall, Nabil Bank, Ghantaghar Branch, Kathmandu')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS tiab_meeting_clubs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    club_id INT UNSIGNED NULL,
    club_name VARCHAR(255) NOT NULL,
    club_area VARCHAR(30) NOT NULL DEFAULT '',
    meeting_number VARCHAR(50) NOT NULL DEFAULT '',
    officers_json TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
);
