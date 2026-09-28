<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/agenda.php';
$pdo = getDB();
if ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new RuntimeException('Native prepares required');
foreach (["' OR 1=1 --", "'; DROP TABLE tiab_meetings; --", '../../config/db.php', '<script>alert(1)</script>'] as $payload) {
    $statement = $pdo->prepare('SELECT ?');
    $statement->execute([$payload]);
    if ($statement->fetchColumn() !== $payload) throw new RuntimeException('Bound value changed');
}
try {
    $pdo->query('SELECT 1; SELECT 2');
    throw new RuntimeException('Multiple statements accepted');
} catch (PDOException $error) {
    if (($error->errorInfo[1] ?? 0) !== 1064) throw new RuntimeException('Unexpected SQL test error');
}
$read = ['tin_clubs', 'tin_fy_club_detail', 'tin_members', 'tin_members_club_role', 'tiab_app_settings'];
$write = ['tiab_meetings', 'tiab_officers', 'tiab_functional_roles', 'tiab_tt_speakers', 'tiab_prepared_speakers', 'tiab_evaluators', 'tiab_agenda_durations', 'tiab_meeting_clubs'];
foreach ($pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
    if (str_contains($grant, 'WITH GRANT OPTION')) throw new RuntimeException('Account may grant privileges');
    if (preg_match('/^GRANT USAGE ON \*\.\* TO /', $grant)) continue;
    if (!preg_match('/^GRANT ([A-Z, ]+) ON `' . preg_quote(DB_NAME, '/') . '`\.`([^`]+)` TO /', $grant, $match)) {
        throw new RuntimeException('Unexpected database-wide/global/role grant');
    }
    $allowed = in_array($match[2], $write, true) ? ['SELECT', 'INSERT', 'UPDATE', 'DELETE'] : (in_array($match[2], $read, true) ? ['SELECT'] : []);
    if (!$allowed || array_diff(explode(', ', $match[1]), $allowed)) throw new RuntimeException('Unexpected table privilege');
}
try {
    $pdo->query('SELECT User FROM mysql.user LIMIT 0');
    throw new RuntimeException('MySQL system table accessible');
} catch (PDOException $error) {
    if (!in_array($error->errorInfo[1] ?? 0, [1044, 1142, 1143], true)) throw new RuntimeException('Unexpected privilege test error');
}
echo "Database security checks passed: native binding, multi-statements blocked, table-only privileges, no FILE/admin/global grants.\n";
foreach (getMeetingSummaries() as $meeting) {
    $summary = buildAgenda($meeting);
    $full = buildAgenda(getMeetingFull((int)$meeting['id']));
    if ($summary['end'] !== $full['end'] || $summary['total'] !== $full['total'] || $summary['day_offset'] !== $full['day_offset']) {
        throw new RuntimeException('List and full agenda schedules disagree');
    }
}
echo "Saved-list and full-agenda schedules agree.\n";
