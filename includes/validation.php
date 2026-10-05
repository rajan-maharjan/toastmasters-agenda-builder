<?php
/** Validate structure before values reach trim(), array offsets, sessions or SQL. */
function validateAgendaInput(array $input): void
{
    $text = static function ($value, int $limit): void {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0") || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Invalid form data: a text field has an invalid format or exceeds its length limit.');
        }
    };
    $fields = [
        'csrf_token' => 64, 'meeting_id' => 10, 'district' => 100, 'division' => 100, 'area' => 100,
        'meeting_number' => 2000, 'theme' => 500, 'meeting_date' => 10, 'start_time' => 5, 'timezone' => 20,
        'mission' => 4000, 'quote_text' => 4000, 'quote_author' => 255, 'venue_details' => 4000,
        'wod_word' => 2000, 'wod_meaning' => 2000, 'wod_synonyms' => 2000, 'wod_example' => 2000,
        'ballot_selection_present' => 1, 'agenda_order' => 2,
    ];
    if (isset($input['agenda_order']) && !in_array($input['agenda_order'], ['TT', 'FS'], true)) throw new InvalidArgumentException('Invalid agenda order.');
    $rows = [
        'clubs' => ['club_id' => 10, 'meeting_number' => 50, 'officers' => []],
        'speakers' => ['speaker_name' => 255, 'topic' => 500, 'level' => 100, 'pathways' => 100, 'duration' => 30],
        'tt_speakers' => ['speaker_name' => 255, 'duration' => 3],
        'evaluators' => ['evaluator_name' => 255, 'speaker_index' => 10, 'speaker_name' => 255, 'duration' => 30],
    ];
    $map = static function ($values, int $limit) use ($text): void {
        if (!is_array($values) || count($values) > 50) throw new InvalidArgumentException('Invalid form data: malformed field group.');
        foreach ($values as $key => $value) {
            if (!preg_match('/^[a-z_]{1,60}$/D', (string)$key)) throw new InvalidArgumentException('Invalid form field name.');
            $text($value, $limit);
        }
    };
    if (strlen(serialize($input)) > 65536) throw new InvalidArgumentException('Agenda form is too large.');
    foreach ($input as $key => $value) {
        if (isset($fields[$key])) { $text($value, $fields[$key]); continue; }
        if ($key === 'roles' || $key === 'agenda_durations') { $map($value, $key === 'roles' ? 255 : 3); continue; }
        if ($key === 'ballot_categories') {
            if (!is_array($value) || count($value) > 4) throw new InvalidArgumentException('Invalid ballot selection.');
            foreach ($value as $category) $text($category, 60);
            continue;
        }
        if (!isset($rows[$key]) || !is_array($value) || count($value) > 50) throw new InvalidArgumentException('Invalid form data: unexpected or oversized field group.');
        foreach ($value as $index => $row) {
            if (!preg_match('/^[0-9]{1,10}$/D', (string)$index) || !is_array($row)) throw new InvalidArgumentException('Invalid participant or club entry.');
            foreach ($row as $field => $entry) {
                if (!array_key_exists($field, $rows[$key])) throw new InvalidArgumentException('Invalid participant or club field.');
                if ($key === 'clubs' && $field === 'officers') $map($entry, 255);
                else $text($entry, $rows[$key][$field]);
            }
        }
    }
}
