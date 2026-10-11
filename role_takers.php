<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
$meeting = getMeetingFull(requestId($_GET, 'id'));
if (!$meeting) { http_response_code(404); exit('Meeting not found.'); }
$labels = ['saa' => 'SAA', 'presiding_officer' => 'Presiding Officer', 'tmoe' => tmoeLabel($meeting['start_time']), 'ge' => 'GE', 'timer' => 'Timer', 'ah_counter' => 'Ah Counter', 'grammarian' => 'Grammarian', 'ballot_counter' => 'Ballot Counter', 'tt_master' => 'Table Topics Master'];
$roles = [];
foreach (buildAgenda($meeting)['blocks'] as $block) {
    if ($block['role'] && !isset($roles[$block['role']])) $roles[$block['role']] = $labels[$block['role']] ?? $block['role'];
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
<table class="role-takers-table"><colgroup><col style="width:16%"><col style="width:18%"><col style="width:66%"></colgroup><thead><tr><th>Role</th><th>Name</th><th>Introduction</th></tr></thead><tbody>
<?php foreach ($roles as $key => $label): ?>
<tr><th scope="row"><?= agendaEscape($label) ?></th><td><?= agendaEscape($meeting['roles'][$key] ?? '') ?></td><td><?= nl2br(agendaEscape($meeting['role_intros'][$key] ?? '')) ?></td></tr>
<?php endforeach; ?>
</tbody></table></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
