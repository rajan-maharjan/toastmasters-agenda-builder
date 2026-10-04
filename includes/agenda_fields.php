<?php
function sheetClub($index, array $club): void
{
    global $activeClubs;
    $prefix = 'clubs[' . $index . ']';
    echo '<div class="club-entry" data-key="' . agendaEscape($index) . '"><span class="club-number"></span>';
    echo '<select class="sheet-input" name="' . agendaEscape($prefix) . '[club_id]" data-club-select aria-label="Club name" required><option value="">Select a club</option>';
    foreach ($activeClubs as $option) {
        echo '<option value="' . agendaEscape($option['club_id']) . '" data-officers="' . agendaEscape(json_encode($option['officer_defaults'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) . '" data-area="' . agendaEscape($option['area']) . '"' . ((string)($club['club_id'] ?? '') === (string)$option['club_id'] ? ' selected' : '') . '>' . agendaEscape($option['name']) . '</option>';
    }
    echo '</select>';
    sheetInput($prefix . '[meeting_number]', $club['meeting_number'] ?? '', 'Club meeting number', true, 'data-club-number maxlength="50" placeholder="Meeting #"');
    echo '<button type="button" class="remove-entry" data-remove aria-label="Remove club">&times;</button></div>';
}

function sheetClubOfficer($index, array $club, string $role, string $label, bool $editable): void
{
    echo '<div class="club-officer-name" data-officer-club="' . agendaEscape($club['club_id'] ?? '') . '" data-club-key="' . agendaEscape($index) . '"><span class="officer-club-label" title="' . agendaEscape($club['name'] ?? '') . '">' . agendaEscape(shortClubName($club['name'] ?? '') ?: 'Club') . '</span>';
    sheetInput('clubs[' . $index . '][officers][' . $role . ']', $club['officers'][$role] ?? '', $label . ' name', $editable, 'placeholder="Name" maxlength="255"');
    echo '</div>';
}

function sheetInput(string $name, $value, string $label, bool $editable, string $extra = ''): void
{
    if ($editable) {
        echo '<input class="sheet-input rm-min-class" name="' . agendaEscape($name) . '" value="' . agendaEscape($value) . '" aria-label="' . agendaEscape($label) . '" ' . $extra . '>';
    } else {
        echo '<span class="sheet-value">' . agendaEscape($value ?: '—') . '</span>';
    }
}

function sheetDuration(string $name, $value, string $label, bool $editable, int $minimum = 0, bool $readonly = false, bool $speaker = false): void
{
    // Preserve speaker ranges: only ranges receive the extra scheduling minute.
    if (!$speaker && empty($GLOBALS['durationDraft'])) {
        try { $value = durationMinutes((string)$value); } catch (InvalidArgumentException $e) {}
    }
    if ($speaker && empty($GLOBALS['durationDraft'])) {
        $value = trim(preg_replace('/\s*(?:minutes|minute|mins|min|m)\s*$/iu', '', (string)$value));
    }
    echo '<span class="duration-cell">';
    if ($editable) {
        $format = $speaker ? 'data-speaker-duration maxlength="30" title="Ranges use the maximum plus 1 minute; single values stay unchanged"' : 'inputmode="numeric" pattern="[0-9]+"';
        sheetInput($name, $value, $label, true, 'data-duration required ' . $format . ' data-min="' . $minimum . '"' . ($readonly ? ' readonly' : ''));
    } else {
        echo '<span>' . agendaEscape($value) . '</span>';
    }
    echo '<span class="duration-unit">min</span></span>';
}

function sheetParticipant(string $kind, $index, array $row, array $speakers, bool $editable, int $position = 1): void
{
    $arrayName = $kind === 'prepared_speakers' ? 'speakers' : $kind;
    $prefix = $arrayName . '[' . $index . ']';
    $duration = $kind === 'tt_speakers' ? '1-2' : ($row['duration'] ?? ($kind === 'evaluators' ? '2-3' : '5-7'));
    echo '<tr class="participant-row" data-key="' . agendaEscape($index) . '">';
    if ($kind === 'tt_speakers') {
        echo '<td data-tt-label>TT Speaker ' . $position . '</td>';
    }
    if ($kind === 'evaluators') {
        echo '<td>';
        sheetInput($prefix . '[evaluator_name]', $row['evaluator_name'] ?? '', 'Evaluator', $editable, 'data-person required placeholder="Evaluator name" maxlength="255"');
        echo '</td><td>';
        if ($editable) {
            $selected = isset($row['speaker_index']) ? (string)$row['speaker_index'] : '';
            if (!isset($row['speaker_index'])) {
                foreach ($speakers as $key => $speaker) {
                    if (($speaker['speaker_name'] ?? '') !== '' && $speaker['speaker_name'] === ($row['speaker_name'] ?? '')) { $selected = (string)$key; break; }
                }
            }
            echo '<select class="sheet-input evaluator-speaker" name="' . agendaEscape($prefix) . '[speaker_index]" aria-label="Speaker for evaluator" data-selected="' . agendaEscape($selected) . '" required><option value="">Select a featured speaker</option>';
            foreach ($speakers as $key => $speaker) {
                if (trim($speaker['speaker_name'] ?? '') !== '') {
                    echo '<option value="' . agendaEscape($key) . '"' . ((string)$key === $selected ? ' selected' : '') . '>' . agendaEscape($speaker['speaker_name']) . '</option>';
                }
            }
            echo '</select>';
        } else {
            echo agendaEscape($row['speaker_name'] ?? '');
        }
        echo '</td>';
    } else {
        echo '<td>';
        sheetInput($prefix . '[speaker_name]', $row['speaker_name'] ?? '', 'Speaker name', $editable,
            'data-person placeholder="' . ($kind === 'tt_speakers' ? 'Given during the meeting' : 'Speaker name') . '" maxlength="255"' . ($kind === 'prepared_speakers' ? ' required' : ''));
        echo '</td>';
        if ($kind === 'prepared_speakers') {
            foreach (['topic' => 'Topic', 'level' => 'Level', 'pathways' => 'Pathways'] as $field => $label) {
                echo '<td>';
                sheetInput($prefix . '[' . $field . ']', $row[$field] ?? '', $label, $editable, 'placeholder="' . $label . '"');
                echo '</td>';
            }
        }
    }
    echo '<td><div class="duration-cell">';
    sheetDuration($prefix . '[duration]', $duration, 'Speaking minutes or range; only ranges add 1 minute', $editable, 1, $kind === 'tt_speakers', true);
    if ($editable) { echo '<button type="button" class="remove-entry" data-remove aria-label="Remove participant" title="Remove participant">&times;</button>'; }
    echo '</div></td></tr>';
}
