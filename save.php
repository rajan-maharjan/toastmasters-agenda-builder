<?php
require_once __DIR__ . '/includes/security.php';
requirePostAndCsrf();
$id = requestId($_POST, 'meeting_id', true);
require_once __DIR__ . '/includes/validation.php';
try { validateAgendaInput($_POST); }
catch (InvalidArgumentException $e) { securityReject(400, $e->getMessage()); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$pdo = getDB();
$meeting = ['agenda_order' => $_POST['agenda_order'] ?? 'TT'];
foreach (['district', 'division', 'area', 'meeting_number', 'theme', 'meeting_date', 'start_time', 'timezone', 'mission', 'quote_text', 'quote_author', 'venue_details'] as $field) {
    $meeting[$field] = trim((string)($_POST[$field] ?? ''));
}
$errors = [];
$categories = isset($_POST['ballot_selection_present']) ? ($_POST['ballot_categories'] ?? []) : array_keys(agendaBallots([]));
$meeting['ballot_categories'] = is_array($categories) ? array_values(array_intersect(array_keys(agendaBallots([])), array_filter($categories, 'is_string'))) : [];
$meeting['ballot_categories_json'] = json_encode($meeting['ballot_categories'], JSON_THROW_ON_ERROR);
foreach (['wod_word', 'wod_meaning', 'wod_synonyms', 'wod_example'] as $field) {
    $value = $_POST[$field] ?? '';
    $meeting[$field] = is_string($value) ? trim($value) : '';
    if (!is_string($value) || mb_strlen($meeting[$field]) > 2000) {
        $errors[] = 'Word of the Day fields must be text of at most 2000 characters each.';
    }
}
foreach (['district', 'division', 'area', 'theme', 'meeting_date', 'start_time'] as $field) {
    if ($meeting[$field] === '') { $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.'; }
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $meeting['start_time'])) { $errors[] = 'Start time must use 24-hour HH:MM format, for example 18:00.'; }
$date = DateTime::createFromFormat('!Y-m-d', $meeting['meeting_date']);
if (!$date || $date->format('Y-m-d') !== $meeting['meeting_date']) { $errors[] = 'Enter a valid meeting date.'; }

$meeting['roles'] = [];
$meeting['agenda_durations'] = [];
foreach (agendaDefinitions() as $definition) {
    [$key, $title, $role, $default] = $definition;
    if ($role) { $meeting['roles'][$role] = trim($_POST['roles'][$role] ?? ''); }
    if (($definition[4] ?? '') === 'group') continue;
    $duration = trim($_POST['agenda_durations'][$key] ?? (string)durationMinutes($default));
    try { wholeDuration($duration); } catch (InvalidArgumentException $e) { $errors[] = $title . ': ' . $e->getMessage(); }
    $meeting['agenda_durations'][$key] = $duration;
}
$meeting['clubs'] = [];
$activeClubs = getActiveClubs();
foreach ($_POST['clubs'] ?? [] as $row) {
    $selected = $activeClubs[(string)($row['club_id'] ?? '')] ?? null;
    if (!$selected) { $errors[] = 'Select an active club from the dropdown for each club entry.'; continue; }
    $club = array_replace($selected, ['meeting_number' => ltrim(trim($row['meeting_number'] ?? ''), '# '), 'officers' => []]);
    if ($club['area'] === '') $errors[] = $club['name'] . ' has no area assigned in the active club catalogue.';
    if (mb_strlen($club['name']) > 255 || mb_strlen($club['meeting_number']) > 50) $errors[] = 'Club names must be at most 255 characters and meeting numbers at most 50.';
    foreach (clubOfficerRoles() as $role => $label) {
        $club['officers'][$role] = trim($row['officers'][$role] ?? '');
        if (mb_strlen($club['officers'][$role]) > 255) $errors[] = $label . ' name must be at most 255 characters.';
    }
    $meeting['clubs'][] = $club;
}
if (!$meeting['clubs']) $errors[] = 'Add at least one club.';
foreach (['district', 'division', 'area'] as $field) {
    if (mb_strlen($meeting[$field]) > 100) $errors[] = ucfirst($field) . ' must be at most 100 characters.';
}
$meeting['meeting_number'] = combinedClubTitle($meeting['clubs']);
$meeting['prepared_speakers'] = [];
foreach ($_POST['speakers'] ?? [] as $key => $row) {
    $speaker = [];
    foreach (['speaker_name', 'topic', 'level', 'pathways', 'duration'] as $field) { $speaker[$field] = trim($row[$field] ?? ''); }
    if ($speaker['speaker_name'] === '') { $errors[] = 'Enter a name for each featured speaker, or remove unused rows.'; }
    try {
        speakerSlotMinutes($speaker['duration']);
    } catch (InvalidArgumentException $e) { $errors[] = 'Featured speaker: ' . $e->getMessage(); }
    $meeting['prepared_speakers'][$key] = $speaker;
}
$meeting['tt_speakers'] = [];
foreach ($_POST['tt_speakers'] ?? [] as $row) {
    try {
        if (!in_array(trim($row['duration'] ?? ''), ['1-2', '2'], true)) throw new InvalidArgumentException('Table Topics duration is 1-2 min.');
    } catch (InvalidArgumentException $e) { $errors[] = 'Table Topics: ' . $e->getMessage(); }
    $meeting['tt_speakers'][] = ['speaker_name' => trim($row['speaker_name'] ?? '') ?: 'Given during the meeting', 'duration' => '1-2'];
}
$meeting['evaluators'] = [];
foreach ($_POST['evaluators'] ?? [] as $row) {
    $name = trim($row['evaluator_name'] ?? '');
    $speakerKey = $row['speaker_index'] ?? null;
    $speaker = $speakerKey !== null ? ($meeting['prepared_speakers'][$speakerKey]['speaker_name'] ?? '') : trim($row['speaker_name'] ?? '');
    if ($name === '') { $errors[] = 'Enter a name for each evaluator, or remove unused rows.'; }
    if ($speaker === '' || !in_array($speaker, array_column($meeting['prepared_speakers'], 'speaker_name'), true)) { $errors[] = 'Each evaluator must select a speaker from Featured Speakers.'; }
    $duration = trim($row['duration'] ?? '');
    try {
        speakerSlotMinutes($duration);
    } catch (InvalidArgumentException $e) { $errors[] = 'Evaluator: ' . $e->getMessage(); }
    $meeting['evaluators'][] = ['evaluator_name' => $name, 'speaker_name' => $speaker, 'duration' => $duration];
}
if ($errors) {
    $_SESSION['errors'] = array_values(array_unique($errors));
    $_SESSION['form_data'] = $_POST;
    header('Location: form.php' . ($id ? '?id=' . $id : '')); exit;
}

// Never trust a posted end time; derive it from every displayed duration.
$meeting['end_time'] = buildAgenda($meeting)['end'];
try {
    $pdo->beginTransaction();
    $fields = ['district', 'division', 'area', 'meeting_number', 'theme', 'meeting_date', 'start_time', 'end_time', 'timezone', 'mission', 'quote_text', 'quote_author', 'venue_details'];
    $fields = array_merge($fields, ['wod_word', 'wod_meaning', 'wod_synonyms', 'wod_example', 'ballot_categories_json', 'agenda_order']);
    $values = array_map(static fn($field) => $meeting[$field], $fields);
    if ($id) {
        $check = $pdo->prepare('SELECT id FROM tiab_meetings WHERE id = ?');
        $check->execute([$id]);
        if (!$check->fetchColumn()) throw new RuntimeException('This agenda no longer exists.');
        $assignments = implode(', ', array_map(static fn($field) => $field . ' = ?', $fields));
        $pdo->prepare('UPDATE tiab_meetings SET ' . $assignments . ', updated_at = NOW() WHERE id = ?')->execute([...$values, $id]);
        foreach (['meeting_clubs', 'functional_roles', 'prepared_speakers', 'tt_speakers', 'evaluators', 'agenda_durations'] as $table) {
            $pdo->prepare('DELETE FROM tiab_' . $table . ' WHERE meeting_id = ?')->execute([$id]);
        }
    } else {
        $pdo->prepare('INSERT INTO tiab_meetings (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')')->execute($values);
        $id = (int)$pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('INSERT INTO tiab_meeting_clubs (meeting_id, club_id, club_name, club_area, meeting_number, officers_json, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($meeting['clubs'] as $i => $club) { $stmt->execute([$id, $club['club_id'], $club['name'], $club['area'], $club['meeting_number'], json_encode($club['officers'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $i]); }
    $stmt = $pdo->prepare('INSERT INTO tiab_functional_roles (meeting_id, role_key, person_name) VALUES (?, ?, ?)');
    foreach ($meeting['roles'] as $key => $person) { if ($person !== '') $stmt->execute([$id, $key, $person]); }
    $stmt = $pdo->prepare('INSERT INTO tiab_agenda_durations (meeting_id, item_key, duration) VALUES (?, ?, ?)');
    foreach ($meeting['agenda_durations'] as $key => $duration) { $stmt->execute([$id, $key, $duration]); }
    $stmt = $pdo->prepare('INSERT INTO tiab_prepared_speakers (meeting_id, speaker_name, topic, level, pathways, duration, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach (array_values($meeting['prepared_speakers']) as $i => $row) { $stmt->execute([$id, $row['speaker_name'], $row['topic'], $row['level'], $row['pathways'], $row['duration'], $i]); }
    $stmt = $pdo->prepare('INSERT INTO tiab_tt_speakers (meeting_id, speaker_name, duration, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($meeting['tt_speakers'] as $i => $row) { $stmt->execute([$id, $row['speaker_name'], $row['duration'], $i]); }
    $stmt = $pdo->prepare('INSERT INTO tiab_evaluators (meeting_id, evaluator_name, speaker_name, duration, sort_order) VALUES (?, ?, ?, ?, ?)');
    foreach ($meeting['evaluators'] as $i => $row) { $stmt->execute([$id, $row['evaluator_name'], $row['speaker_name'], $row['duration'], $i]); }
    $pdo->commit();
    securityAudit('agenda_saved', $id);
    $_SESSION['success'] = 'Agenda saved successfully!';
    header('Location: view.php?id=' . $id . '&saved=1');
} catch (PDOException | RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log((string)$e);
    $_SESSION['errors'] = ['Could not save the agenda. Please try again later.'];
    $_SESSION['form_data'] = $_POST;
    header('Location: form.php' . (!empty($_POST['meeting_id']) ? '?id=' . (int)$_POST['meeting_id'] : ''));
}
exit;
