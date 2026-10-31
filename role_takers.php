<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
$meeting = getMeetingFull(requestId($_GET, 'id'));
if (!$meeting) { http_response_code(404); exit('Meeting not found.'); }
$labels = ['saa' => 'SAA', 'presiding_officer' => 'Presiding Officer', 'tmoe' => tmoeLabel($meeting['start_time']), 'ge' => 'GE', 'timer' => 'Timer', 'ah_counter' => 'Ah Counter', 'grammarian' => 'Grammarian', 'ballot_counter' => 'Ballot Counter', 'tt_master' => 'Table Topics Master'];
$roles = [];
$introductions = [];
$pdo = getDB();
$memberLookup = $pdo->prepare('SELECT member_id, full_name, intro FROM tin_members WHERE TRIM(full_name) = ?');
$memberIdLookup = $pdo->prepare('SELECT member_id, intro FROM tin_members WHERE member_id = ?');
foreach (buildAgenda($meeting)['blocks'] as $block) {
    if ($block['role'] && !isset($roles[$block['role']])) {
        $roles[$block['role']] = true;
        $name = $meeting['roles'][$block['role']] ?? '';
        $intro = trim($meeting['role_intros'][$block['role']] ?? '');
        $memberId = $meeting['role_member_ids'][$block['role']] ?? '';
        if ($memberId !== '') {
            $memberIdLookup->execute([$memberId]);
            $profiles = $memberIdLookup->fetchAll();
            if (count($profiles) === 1) $intro = trim($profiles[0]['intro'] ?? '');
        }
        $introductions[] = ['role' => $labels[$block['role']] ?? $block['role'], 'name' => $name, 'intro' => $intro];
    }
    if ($block['kind'] === 'group') {
        $isEvaluator = $block['key'] === 'evaluators';
        $field = $isEvaluator ? 'evaluator_name' : 'speaker_name';
        $label = $isEvaluator ? 'Evaluator' : ($block['key'] === 'tt_speakers' ? 'TT Speaker' : 'Prepared Speaker');
        foreach (array_values($block['rows']) as $i => $row) {
            $name = trim($row[$field] ?? '');
            if ($name === '' || strcasecmp($name, 'Given during the meeting') === 0) {
                if ($block['key'] === 'tt_speakers') $introductions[] = ['role' => $label . ' ' . ($i + 1), 'name' => 'Given during the meeting', 'intro' => 'Speaker and introduction to be confirmed.'];
                continue;
            }
            $memberLookup->execute([$name]);
            $matches = $memberLookup->fetchAll();
            // Older participant rows store names; never choose an ambiguous biography.
            if (count($matches) === 1) {
                $intro = trim($matches[0]['intro'] ?? '');
            } elseif (!$matches) {
                $intro = 'No matching member profile found. Select this speaker or evaluator from the member suggestions in the agenda.';
            } else {
                $intro = 'Multiple member profiles share this name. The introduction could not be identified reliably.';
            }
            $introductions[] = ['role' => $label . ' ' . ($i + 1), 'name' => $name, 'intro' => $intro];
        }
    }
}
$pageTitle = 'Role Taker Introductions';
$editable = false;
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/role_takers.css">
<div class="builder-toolbar no-print"><h1>Role Taker Introductions</h1><div class="builder-actions"><a class="btn btn-outline-primary" href="view.php?id=<?= (int)$meeting['id'] ?>">Back to agenda</a><button type="button" class="btn btn-primary" data-print-page>Print / Save PDF</button></div></div>
<section class="role-takers-sheet">
<h2><?= agendaEscape(agendaTitle($meeting)) ?></h2>
<p><?= agendaEscape($meeting['meeting_date']) ?> | <?= agendaEscape($meeting['theme']) ?></p>
<p class="introduction-edit-tip no-print">Double-click any blank or filled introduction to edit it for this page and print. Changes are not saved to the member profile.</p>
<table class="role-takers-table">
    <colgroup><col style="width:16%">
        <col style="width:18%"><col style="width:66%">
    </colgroup>
    <thead>
        <tr><th>Role</th><th>Name</th><th>Introduction</th></tr>
    </thead>
    <tbody>
    <?php foreach ($introductions as $entry): ?>
        <tr>
            <th scope="row"><?= agendaEscape($entry['role']) ?></th>
            <td><?= agendaEscape($entry['name']) ?></td>
            <td class="introduction-cell" data-introduction-cell>
                <span class="<?= trim($entry['intro']) === '' ? 'intro-empty-label' : '' ?>" data-introduction-display><?= trim($entry['intro']) === '' ? 'No introduction - double-click to add one.' : agendaEscape($entry['intro']) ?></span>
                <textarea class="introduction-input no-print" data-introduction-input maxlength="1500" rows="3" aria-label="Edit introduction for <?= agendaEscape($entry['name']) ?>" placeholder="Enter an introduction" hidden></textarea>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody></table></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
