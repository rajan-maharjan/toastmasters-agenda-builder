<?php
require_once __DIR__ . '/includes/security.php';
$id = requestId($_GET, 'id', true) ?? 0;
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
$settings = getAppSettings();
$meeting = $id > 0 ? getMeetingFull($id) : null;
if ($id > 0 && !$meeting) { header('Location: index.php'); exit; }
$editMode = $meeting !== null;
if (!$meeting) {
    $meeting = [
        'agenda_order' => ($_GET['orderby'] ?? 'TT') === 'FS' ? 'FS' : 'TT',
        'district' => $settings['default_district'] ?? 'District 41',
        'division' => $settings['default_division'] ?? 'Division C',
        'area' => $settings['default_area'] ?? 'Area C1',
        'meeting_date' => (new DateTime('today'))->format('Y-m-d'),
        'start_time' => '18:00', 'timezone' => $settings['default_timezone'] ?? 'NPT',
        'mission' => $settings['default_mission'] ?? '',
        'quote_text' => $settings['default_quote'] ?? '', 'quote_author' => $settings['default_quote_author'] ?? '',
        'venue_details' => str_replace(['6:00 pm', '\\n'], ['18:00', "\n"], $settings['default_venue'] ?? ''),
        'clubs' => [['club_id' => '', 'name' => '', 'area' => '', 'meeting_number' => '', 'officers' => []]],
        'prepared_speakers' => array_fill(0, 3, ['speaker_name' => '', 'duration' => '5-7 mins']),
        'tt_speakers' => array_fill(0, 3, ['speaker_name' => '', 'duration' => '1-2 minutes']),
        'evaluators' => array_fill(0, 3, ['evaluator_name' => '', 'speaker_name' => '', 'duration' => '2-3 mins']),
    ];
}
$hasDraft = isset($_SESSION['form_data']) && (int)($_SESSION['form_data']['meeting_id'] ?? 0) === $id;
$errors = $hasDraft ? ($_SESSION['errors'] ?? []) : [];
if ($hasDraft) {
    $draft = $_SESSION['form_data'];
    $durationDraft = true;
    if (isset($draft['ballot_selection_present'])) $draft['ballot_categories'] = $draft['ballot_categories'] ?? [];
    $draft['prepared_speakers'] = $draft['speakers'] ?? [];
    foreach (['clubs', 'tt_speakers', 'evaluators', 'roles', 'agenda_durations'] as $key) { $draft[$key] = $draft[$key] ?? []; }
    $meeting = array_replace($meeting, $draft);
    // Render invalid input intact, using valid defaults only for the initial schedule calculation.
    $invalidDurations = $meeting['agenda_durations'];
    foreach ($meeting['agenda_durations'] as $key => $duration) {
        try { durationMinutes($duration); } catch (InvalidArgumentException $e) { unset($meeting['agenda_durations'][$key]); }
    }
}
if ($hasDraft) unset($_SESSION['errors'], $_SESSION['form_data']);
$activeClubs = getActiveClubs();
foreach ($meeting['clubs'] as &$club) {
    $selected = $activeClubs[(string)($club['club_id'] ?? '')] ?? null;
    // Recognize older saved selections by name when no catalogue ID was stored.
    if (!$selected && empty($club['club_id']) && !empty($club['name'])) {
        foreach ($activeClubs as $candidate) {
            if (strcasecmp(shortClubName($candidate['name']), shortClubName($club['name'])) === 0) { $selected = $candidate; break; }
        }
    }
    if ($selected) {
        $club = array_replace($club, $selected);
        if (!isset($draft)) {
            foreach ($selected['officer_defaults'] as $role => $name) {
                if (trim($club['officers'][$role] ?? '') === '') $club['officers'][$role] = $name;
            }
        }
    }
    else { $club['club_id'] = ''; $club['name'] = ''; $club['area'] = ''; }
}
unset($club);
$editable = true;
$pageTitle = $editMode ? 'Edit Agenda' : 'Agenda Builder';
require __DIR__ . '/includes/header.php';
?>
<div class="builder-toolbar no-print">
    <div><h1><?= $pageTitle ?></h1><p>Edit the sheet below. Times update as you change durations.</p></div>
    <div class="builder-actions"><button type="submit" form="agenda-form" class="btn btn-primary">Save &amp; Preview</button></div>
</div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check the agenda:</strong><ul><?php foreach ($errors as $error): ?><li><?= agendaEscape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form action="save.php" method="POST" id="agenda-form">
    <?= csrfField() ?>
    <?php if ($editMode): ?><input type="hidden" name="meeting_id" value="<?= $id ?>"><?php endif; ?>
    <?php require __DIR__ . '/includes/agenda_sheet.php'; ?>
    <div class="builder-bottom no-print"><a href="index.php" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary">Save &amp; Preview</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
