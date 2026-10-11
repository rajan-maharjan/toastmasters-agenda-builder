<?php
require_once __DIR__ . '/agenda_fields.php';
$schedule = buildAgenda($meeting, $editable);
$speakers = $meeting['prepared_speakers'] ?? [];
$clubs = $meeting['clubs'] ?? [];
?>
<section class="agenda-sheet <?= $editable ? 'is-editable' : '' ?>" aria-label="Meeting agenda">
    <?php if ($editable): ?><input type="hidden" name="agenda_selection_present" value="1"><?php endif; ?>
    <div class="sheet-meta">
        <div class="district-fields">
            <?php foreach (['district' => 'District', 'division' => 'Division', 'area' => 'Area'] as $field => $label): ?>
                <?php sheetInput($field, $meeting[$field] ?? '', $label, $editable, 'required maxlength="100" placeholder="' . $label . '"'); ?>
            <?php endforeach; ?>
        </div>
        <div class="meeting-clock">
            <?php if ($editable): ?>
                <input class="sheet-input" type="date" name="meeting_date" aria-label="Meeting date" value="<?= agendaEscape($meeting['meeting_date']) ?>" required>
                <input class="sheet-input time-input" type="time" step="60" name="start_time" id="start_time" aria-label="Meeting start time" value="<?= agendaEscape(isset($draft) ? $meeting['start_time'] : $schedule['start']) ?>" required>
            <?php else: ?>
                <span><?= agendaEscape(date('d M Y', strtotime($meeting['meeting_date']))) ?></span>
                <span><?= $schedule['start'] ?></span>
            <?php endif; ?>
            <span>–</span><output id="computed-end" class="end-time" aria-label="Calculated end time"><?= $schedule['end'] ?></output>
            <span id="end-day"><?= $schedule['day_offset'] ? '(+' . $schedule['day_offset'] . ' day)' : '' ?></span>
            <?php sheetInput('timezone', $meeting['timezone'] ?? 'NPT', 'Timezone', $editable, 'maxlength="20"'); ?>
        </div>
    </div>
    <div class="sheet-title"><span id="agenda-title" aria-live="polite"><?= agendaEscape(agendaTitle($meeting)) ?></span></div>
    <div class="sheet-theme"><strong>Theme:</strong><?php sheetInput('theme', $meeting['theme'] ?? '', 'Meeting theme', $editable, 'required placeholder="Enter your meeting theme"'); ?></div>
    <?php if ($editable): ?>
        <section class="clubs-editor" aria-label="Clubs on this agenda">
            <div class="clubs-heading"><h2>Clubs on this agenda</h2><button type="button" class="add-entry" data-add="clubs">+ Joint Meeting? Add Clubs</button></div>
            <div class="clubs-list" data-list="clubs"><?php foreach ($clubs as $i => $club) { sheetClub($i, $club); } ?></div>
            <?php if (!$activeClubs): ?><p role="alert">No active clubs are available in the club catalogue.</p><?php endif; ?>
        </section>
    <?php endif; ?>
    <div class="sheet-body">
        <aside class="sheet-sidebar">
            <h2>Club Officers</h2>
            <div id="officers-container">
                <?php foreach (clubOfficerRoles() as $role => $label): ?>
                    <div class="officer-entry" data-officer-role="<?= $role ?>"><strong><?= $label ?></strong>
                        <?php foreach ($clubs as $i => $club) { sheetClubOfficer($i, $club, $role, $label, $editable); } ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach (['mission' => 'Our Mission', 'quote_text' => 'Quote', 'venue_details' => 'Meeting Details'] as $field => $label): ?>
                <?php if ($editable || !empty($meeting[$field])): ?>
                    <section class="sidebar-note"><h3><?= $label ?></h3>
                    <?php if ($editable): ?>
                        <textarea class="sheet-input" name="<?= $field ?>" aria-label="<?= $label ?>" rows="<?= $field === 'mission' ? 6 : 3 ?>"><?= agendaEscape($meeting[$field] ?? '') ?></textarea>
                    <?php else: ?><p><?= nl2br(agendaEscape($meeting[$field])) ?></p><?php endif; ?>
                    <?php if ($field === 'quote_text'): ?>
                        <?php if ($editable): ?>
                            <label>Quote author<?php sheetInput('quote_author', $meeting['quote_author'] ?? '', 'Quote author', true, 'maxlength="255" placeholder="Author"'); ?></label>
                        <?php elseif (!empty($meeting['quote_author'])): ?><p class="quote-author">— <?= agendaEscape($meeting['quote_author']) ?></p><?php endif; ?>
                    <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="sidebar-link" href="https://www.toastmasters.org">www.toastmasters.org</a>
        </aside>
        <div class="sheet-main">
            <?php if ($editable): ?><p class="schedule-note">Check / Uncheck an agenda item to add/remove it from the preview and Print Agenda.</p><?php endif; ?>
            <table class="schedule-table" aria-label="Agenda schedule">
                <colgroup><col class="time-col"><col class="item-col"><col class="role-col"><col class="duration-col"></colgroup>
                <thead><tr><th>Time</th><th>Agenda Item</th><th>Role Taker</th><th>Duration</th></tr></thead>
                <?php foreach ($schedule['blocks'] as $block): ?>
                    <tbody class="schedule-block" data-kind="<?= $block['kind'] ?>" data-key="<?= $block['key'] ?>">
                        <tr class="<?= $block['kind'] === 'group' ? 'segment-heading' : 'agenda-line' ?>">
                            <td class="agenda-clock">
                                <?php if ($editable && in_array($block['key'], optionalAgendaKeys(), true)): ?>
                                    <input style="float:left;margin-right:5px;" type="checkbox" data-section-toggle name="agenda_sections[]" value="<?= $block['key'] ?>" aria-label="Include <?= agendaEscape($block['title']) ?>" <?= $block['enabled'] ? 'checked' : '' ?>>
                                <?php endif; ?><span data-clock><?= $block['enabled'] ? $block['time'] : '?' ?></span>
                            </td>
                            <?php if ($block['kind'] === 'group'): ?>
                                <th colspan="2" scope="row"><?= $block['title'] ?></th><td class="segment-total" data-total><?= $block['duration'] ?></td>
                            <?php else: ?>
                                <td><?php if ($editable && in_array($block['key'], editableAgendaKeys(), true)): ?>
                                    <?php sheetInput('agenda_titles[' . $block['key'] . ']', $block['title'], 'Agenda description', true, 'data-agenda-title maxlength="500" required'); ?>
                                <?php else: ?><span data-agenda-title><?= agendaEscape($block['title']) ?></span><?php endif; ?></td>
                                <td><?php if ($editable && $block['role']): ?>
                                    <input class="sheet-input role-input" required role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="role-member-results" name="role_labels[<?= $block['role'] ?>]" data-role="<?= $block['role'] ?>" aria-label="<?= agendaEscape(str_replace('_', ' ', $block['role'])) ?> name" data-member-id="<?= agendaEscape($meeting['role_member_ids'][$block['role']] ?? '') ?>" value="<?= agendaEscape($block['person']) ?>" placeholder="Search member/club name" autocomplete="off">
                                <?php else: ?><span class="role-name"><?= agendaEscape($block['person']) ?></span><?php endif; ?></td>
                                <td>
                                    <?php if($block['key']!='networking'): ?>    
                                        <?php sheetDuration('agenda_durations[' . $block['key'] . ']', $invalidDurations[$block['key']] ?? $block['duration'], $block['title'] . ' duration', $editable); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php if ($block['key'] === 'grammarian_intro'): ?>
                            <tr><td colspan="4" class="wod-content">
                                <section aria-label="Word of the Day">
                                    <h2>Word of the Day</h2>
                                    <div class="wod-grid">
                                        <?php foreach (['wod_word' => ['Word', 'e.g. Manifest'],  'wod_synonyms' => ['Synonyms', 'Synonyms'], 'wod_meaning' => ['Meaning', 'Meaning of the word'], 'wod_example' => ['Example', 'Example sentence']] as $field => [$label, $placeholder]): ?>
                                            <div class="wod-field"><strong><?= $label ?></strong><?php sheetInput($field, $meeting[$field] ?? '', 'Word of the Day: ' . $label, $editable, 'maxlength="2000" placeholder="' . $placeholder . '"'); ?></div>
                                        <?php endforeach; ?>                                        
                                    </div>

                                    
                                </section>
                            </td></tr>
                        <?php endif; ?>
                        <?php if ($block['kind'] === 'group'): ?>
                            <tr><td colspan="4" class="segment-content">
                                <table class="participant-table <?= $block['key'] ?>" aria-label="<?= $block['title'] ?>">
                                    <thead><tr>
                                        <?php $columns = $block['key'] === 'prepared_speakers' ? ['Speaker', 'Topic', 'Level', 'Pathways', 'Time'] : ($block['key'] === 'evaluators' ? ['Evaluator', 'Speaker', 'Time'] : ['Table Topic Speaker', ' Title of Speech', 'Time']); ?>
                                        <?php foreach ($columns as $column): ?><th scope="col"><?= $column ?></th><?php endforeach; ?>
                                    </tr></thead>
                                    <tbody data-list="<?= $block['key'] ?>">
                                        <?php $position = 1; foreach ($block['rows'] as $i => $row) { sheetParticipant($block['key'], $i, $editable ? array_replace($row, $meeting[$block['key']][$i]) : $row, $speakers, $editable, $position++); } ?>
                                    </tbody>
                                </table>
                                <?php if ($editable): ?><button type="button" class="add-entry" data-add="<?= $block['key'] ?>">+ Add <?= $block['key'] === 'evaluators' ? 'evaluator' : 'speaker' ?></button><?php endif; ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
            <div class="schedule-note"><span>Total Meeting Time</span><strong><output id="total-minutes"><?= $schedule['total'] ?></output> minutes</strong></div>
            <section class="ballot-sheet" aria-label="Ballot sheet">
                <h2>Ballot Sheet</h2>
                <?php $selected = selectedBallots($meeting); ?>
                <?php if ($editable): ?><input type="hidden" name="ballot_selection_present" value="1"><p>Untick the categories if you DO NOT want to include in the PDF.</p><?php endif; ?>
                <div class="ballot-row">
                    <?php foreach (agendaBallots($meeting) as $label => $candidates): ?>
                        <?php if (!$editable && !in_array($label, $selected, true)) continue; ?>
                        <div class="ballot-block"><h3><?php if ($editable): ?><label><input type="checkbox" data-ballot-explicit="<?= isset($meeting['ballot_categories']) || is_array(json_decode($meeting['ballot_categories_json'] ?? 'null', true)) ? '1' : '0' ?>" name="ballot_categories[]" value="<?= agendaEscape($label) ?>" <?= in_array($label, $selected, true) ? 'checked' : '' ?>> <?= $label ?></label><?php else: ?><?= $label ?><?php endif; ?></h3><ul>
                            <?php foreach ($candidates as $candidate): ?><li><span class="ballot-checkbox"></span><span><?= agendaEscape($candidate) ?></span></li><?php endforeach; ?>
                        </ul></div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</section>
<?php if ($editable): ?>
    <?php foreach (['tt_speakers','prepared_speakers', 'evaluators'] as $kind): ?>
        <template id="template-<?= $kind ?>"><?php sheetParticipant($kind, '__INDEX__', [], [], true); ?></template>
    <?php endforeach; ?>
    <template id="template-clubs"><?php sheetClub('__INDEX__', []); ?></template>
    <?php foreach (clubOfficerRoles() as $role => $label): ?>
        <template id="template-officer-<?= $role ?>"><?php sheetClubOfficer('__INDEX__', [], $role, $label, true); ?></template>
    <?php endforeach; ?>
    <dialog id="new-member-dialog">
        <h2>Add a new member</h2>
        <p>Use the membership ID from Toastmasters Portal. The member will be linked to the selected agenda club.</p>
        <label>Member ID from TI * <input data-new-member="member_id" inputmode="numeric" maxlength="10"></label>
        <label>Full name * <input data-new-member="full_name" maxlength="500"></label>
        <label>Club * <select data-new-member="club_id"><option value="">Select a club</option><?php foreach (getDB()->query('SELECT club_id, club_name FROM tin_clubs ORDER BY club_name, club_id') as $clubOption): ?><option value="<?= agendaEscape($clubOption['club_id']) ?>"><?= agendaEscape($clubOption['club_name']) ?></option><?php endforeach; ?></select></label>
        <label>Gender <select data-new-member="gender"><option value="">Select</option><option value="M">Male</option><option value="F">Female</option></select></label>
        <label>Introduction (optional) <textarea data-new-member="intro" maxlength="1500" rows="4"></textarea></label>
        <p data-member-error role="status"></p>
        <button type="button" data-create-member class="btn btn-primary">Add member</button>
        <button type="button" data-cancel-member class="btn btn-outline-secondary">Cancel</button>
    </dialog>
    <datalist id="role-member-options">
        <?php foreach ($roleMembers ?? [] as $member): ?><option value="<?= agendaEscape($member['label']) ?>" data-club-ids="<?= agendaEscape(json_encode($member['club_ids'])) ?>" data-member-id="<?= agendaEscape($member['member_id']) ?>" data-name="<?= agendaEscape($member['full_name']) ?>"></option><?php endforeach; ?>
    </datalist>
    <?php $roleKeys = array_unique(array_filter(array_column(agendaDefinitions(), 2))); ?>
    <?php foreach ($roleKeys as $role): ?><input type="hidden" data-role-value="<?= $role ?>" name="roles[<?= $role ?>]" value="<?= agendaEscape($meeting['role_member_ids'][$role] ?? '') ?>"><?php endforeach; ?>
<?php endif; ?>
