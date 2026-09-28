<?php

$databaseConfig = is_file(__DIR__ . '/database.local.php') ? require __DIR__ . '/database.local.php' : [];
define('DB_HOST', getenv('AGENDA_DB_HOST') ?: ($databaseConfig['host'] ?? 'localhost'));
define('DB_NAME', getenv('AGENDA_DB_NAME') ?: ($databaseConfig['name'] ?? 'toasmaster_agenda'));
define('DB_USER', getenv('AGENDA_DB_USER') ?: ($databaseConfig['user'] ?? ''));
define('DB_PASS', getenv('AGENDA_DB_PASS') !== false ? getenv('AGENDA_DB_PASS') : ($databaseConfig['password'] ?? ''));
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO connection singleton
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        ];

        try {
            if (DB_USER === '' || (PHP_SAPI !== 'cli' && (DB_USER === 'root' || DB_PASS === ''))) {
                throw new RuntimeException('Configure a dedicated database account with a password.');
            }
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log((string)$e);
            http_response_code(503);
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Database Connection Error</title>
                <style>
                    body { font-family: sans-serif; padding: 20px; background-color: #f8f9fa; }
                    .error-box { background: #fff; padding: 20px; border: 1px solid #dc3545; border-radius: 5px; color: #721c24; background-color: #f8d7da; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h2>Database Connection Failed</h2>
                    <p>Could not connect to the database. Please check your configuration.</p>
                </div>
            </body>
            </html>';
            exit;
        }
    }

    return $pdo;
}

/**
 * Get all app settings
 */
function getAppSettings(): array
{
    $pdo = getDB();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM tiab_app_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/** Active club catalogue supplied by the club and financial-year detail tables. */
function getActiveClubs(): array
{
    $rows = getDB()->query("SELECT DISTINCT c.club_id, c.club_name AS name, cd.this_fy_area AS area
        FROM tin_clubs c INNER JOIN tin_fy_club_detail cd ON c.club_id = cd.club_id
        AND c.is_active = '1' AND cd.is_active = '1'
        ORDER BY c.club_name, c.club_id")->fetchAll();
    $clubs = [];
    foreach ($rows as $club) {
        $club['area'] = strtoupper(trim($club['area'] ?? ''));
        $clubs[(string)$club['club_id']] = $club;
    }
    $roleMap = ['PREZ' => 'president', 'VPE' => 'vpe', 'VPM' => 'vpm', 'VPPR' => 'vppr',
        'SECR' => 'secretary', 'TRES' => 'treasurer', 'SAA' => 'saa', 'IPP' => 'ipp'];
    $officers = getDB()->query("SELECT DISTINCT a.club_id, a.role_code, m.member_id, m.full_name
        FROM tin_members_club_role a
        INNER JOIN tin_members m ON a.member_id = m.member_id
        INNER JOIN tin_clubs c ON a.club_id = c.club_id
        WHERE a.fy_id = 5 AND a.role_code IN ('PREZ','VPE','VPM','VPPR','SECR','TRES','SAA','IPP')
        ORDER BY a.club_id, a.role_code, m.full_name, m.member_id")->fetchAll();
    $names = [];
    foreach ($officers as $officer) {
        $clubId = (string)$officer['club_id'];
        $role = $roleMap[$officer['role_code']];
        $name = trim($officer['full_name'] ?? '');
        if (isset($clubs[$clubId]) && $name !== '') $names[$clubId][$role][] = $name;
    }
    foreach ($clubs as $clubId => &$club) {
        $club['officer_defaults'] = array_fill_keys(array_values($roleMap), '');
        foreach ($names[$clubId] ?? [] as $role => $roleNames) {
            $club['officer_defaults'][$role] = implode(', ', array_unique($roleNames));
        }
    }
    unset($club);
    return $clubs;
}

/**
 * Get full meeting details
 */
function getMeetingFull(int $id): ?array
{
    $pdo = getDB();

    // Get meeting
    $stmt = $pdo->prepare("SELECT * FROM tiab_meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        return null;
    }

    // Get officers
    $stmt = $pdo->prepare("SELECT *, officer_name AS name FROM tiab_officers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$id]);
    $meeting['officers'] = $stmt->fetchAll();

    // Get functional roles
    $stmt = $pdo->prepare("SELECT role_key, person_name FROM tiab_functional_roles WHERE meeting_id = ?");
    $stmt->execute([$id]);
    $roles = [];
    while ($row = $stmt->fetch()) {
        $roles[$row['role_key']] = $row['person_name'];
    }
    $meeting['roles'] = $roles;

    // Get TT speakers
    $stmt = $pdo->prepare("SELECT * FROM tiab_tt_speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$id]);
    $meeting['tt_speakers'] = $stmt->fetchAll();

    // Get prepared speakers
    $stmt = $pdo->prepare("SELECT * FROM tiab_prepared_speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$id]);
    $meeting['prepared_speakers'] = $stmt->fetchAll();

    // Get evaluators
    $stmt = $pdo->prepare("SELECT * FROM tiab_evaluators WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$id]);
    $meeting['evaluators'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT item_key, duration FROM tiab_agenda_durations WHERE meeting_id = ?");
    $stmt->execute([$id]);
    $meeting['agenda_durations'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare('SELECT club_id, club_name AS name, club_area AS area, meeting_number, officers_json FROM tiab_meeting_clubs WHERE meeting_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $meeting['clubs'] = $stmt->fetchAll();
    foreach ($meeting['clubs'] as &$club) {
        $club['officers'] = json_decode($club['officers_json'], true) ?: [];
        unset($club['officers_json']);
    }
    unset($club);
    return $meeting;
}

/**
 * Add minutes to time string
 */
function getMeetingSummaries(): array
{
    $pdo = getDB();
    $meetings = [];
    foreach ($pdo->query('SELECT * FROM tiab_meetings ORDER BY meeting_date DESC, created_at DESC') as $meeting) {
        $meetings[$meeting['id']] = $meeting;
    }
    // Load only schedule inputs in batches; avoid full catalogue/officer queries per meeting.
    foreach (['prepared_speakers', 'tt_speakers', 'evaluators'] as $kind) {
        foreach ($pdo->query('SELECT meeting_id, duration FROM tiab_' . $kind) as $row) {
            if (isset($meetings[$row['meeting_id']])) $meetings[$row['meeting_id']][$kind][] = ['duration' => $row['duration']];
        }
    }
    foreach ($pdo->query('SELECT meeting_id, item_key, duration FROM tiab_agenda_durations') as $row) {
        if (isset($meetings[$row['meeting_id']])) $meetings[$row['meeting_id']]['agenda_durations'][$row['item_key']] = $row['duration'];
    }
    return array_values($meetings);
}

function addMins(string $time, int $mins): string
{
    $timestamp = strtotime($time);
    $newTimestamp = $timestamp + ($mins * 60);
    return date('H:i', $newTimestamp);
}
