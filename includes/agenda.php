<?php
// Shared schedule rules used by the builder, saved preview and PDF.
function clubOfficerRoles(): array
{
    return ['president' => 'President', 'vpe' => 'Vice President Education',
        'vpm' => 'Vice President Membership', 'vppr' => 'Vice President Public Relations',
        'secretary' => 'Secretary', 'treasurer' => 'Treasurer',
        'saa' => 'Sergeant at Arms', 'ipp' => 'Immediate Past President'];
}

function shortClubName(string $name): string
{
    $name = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($name));
    return trim(preg_replace('/\s+Toastmasters Club$/iu', '', $name));
}

function clubLocation(array $clubs): array
{
    $areas = [];
    $divisions = [];
    foreach ($clubs as $club) {
        $area = strtoupper(trim($club['area'] ?? ''));
        if ($area === '') continue;
        $areas[] = $area;
        $divisions[] = mb_substr($area, 0, 1);
    }
    $areas = array_values(array_unique($areas));
    $divisions = array_values(array_unique($divisions));
    return ['area' => $areas ? 'Area ' . implode(', ', $areas) : '',
        'division' => $divisions ? 'Division ' . implode(', ', $divisions) : ''];
}

function combinedClubTitle(array $clubs): string
{
    $parts = [];
    foreach ($clubs as $club) {
        $name = shortClubName($club['name'] ?? '');
        if ($name === '') continue;
        $number = ltrim(trim($club['meeting_number'] ?? ''), '# ');
        $parts[] = ($number !== '' ? '#' . $number . ' ' : '') . $name;
    }
    if (count($parts) < 2) return $parts[0] ?? '';
    $last = array_pop($parts);
    return implode(', ', $parts) . ' and ' . $last;
}

function agendaTitle(array $meeting): string
{
    $suffix = !empty($meeting['clubs']) ? combinedClubTitle($meeting['clubs']) : ($meeting['meeting_number'] ?? '');
    return 'Agenda for Meeting' . ($suffix !== '' ? ' ' . $suffix : '');
}

function agendaDefinitions(string $orderby = 'TT'): array
{

    $tableTopicSection = [
        ['topics_intro', 'TMoE continues about theme and introduces the Table Topics Master', 'tt_master', '2 min'],
        ['tt_speakers', 'Table Topic Session', '', '', 'group'],
    ];

    $preparedSpeakerSection = [
        ['tmoe_featured_speaker_intro', 'TMoE continues the theme and introduces the prepared speakers', 'tmoe', '2 min'],
        ['prepared_speakers', 'Prepared Speech Session', '', '', 'group'],
    ];

    if ($orderby === 'FS') {
        $TTFeaturedSpeakerSection = array_merge($preparedSpeakerSection, $tableTopicSection);
    } else {
        $TTFeaturedSpeakerSection = array_merge($tableTopicSection, $preparedSpeakerSection);
    }

    return array_merge(
        [
            ['saa_intro', 'Sergeant at Arms - prepares the meeting and greets guests', 'saa', '3 mins'],
            ['presiding_officer_intro', 'Presiding Officer calls the meeting to order, welcome guests and hand overs to TMoE', 'presiding_officer', '3 mins'],
            ['tmoe_intro', 'TMoE introduces the theme and meeting sections', 'tmoe', '3 mins'],
            ['ge_intro', 'General Evaluator introduces the session and the TAG team', 'ge', '2 mins'],
            ['timer_intro', 'Timer explains the role', 'timer', '1 min'],
            ['ah_intro', 'Ah-Counter explains the role', 'ah_counter', '1 min'],
            ['grammarian_intro', 'Grammarian explains the role and introduces WOD', 'grammarian', '2 mins'],
            ['ballot_intro', 'Ballot Counter explains voting rules', 'ballot_counter', '1 min'],
        ],
        $TTFeaturedSpeakerSection,        
        [
            ['evaluation_handover', 'TMoE calls for ballot collection and Talk about Theme', 'tmoe', '1 min'],
            ['evaluation_break', 'Evaluation Break and TMoE continues on theme', 'tmoe', '5 min'],       
            ['evaluators_intro', 'GE introduces the evaluation session', 'ge', '1 min'],
            ['evaluators', 'Prepared Speech Evaluation Session', '', '', 'group'],
            ['ballot_collection', 'GE calls for ballot collection', 'ballot_counter', '1 min'],
            ['ah_report', 'GE calls for the Ah-Counter report', 'ah_counter', '1-2 min'],
            ['grammarian_report', 'GE calls for the Grammarian report', 'grammarian', '1-2 mins'],
            ['general_evaluation', 'GE provides meeting evaluations', 'ge', '3-5 mins'],
            ['timer_report', 'GE calls for the Timer report', 'timer', '1-2 min'],
            ['conclusion', 'GE returns control to the TMoE; TMoE concludes the theme', 'tmoe', '1 min'],
            ['awards', 'Presentation of awards by the Ballot Counter', 'ballot_counter', '3 mins'],
            ['adjourn', 'Presiding Officer adjourns the meeting', 'presiding_officer', '2 mins'],
            ['networking', 'Networking and group photograph', '', '0 mins'],
        ]
    );
}

/** Use the upper bound of a minute range, never silently guess invalid input. */
function durationMinutes(string $value): int
{
    if (!preg_match('/^\s*(\d+)(?:\s*[-–—]\s*(\d+))?\s*(?:m|min|mins|minute|minutes)?\s*$/iu', $value, $matches)) {
        throw new InvalidArgumentException('Use minutes such as 3 mins or 5-7 mins.');
    }
    $lower = (int)$matches[1];
    $upper = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : $lower;
    if ($upper < $lower || $upper > 240) {
        throw new InvalidArgumentException('Durations must be an ascending range between 0 and 240 minutes.');
    }
    return $upper;
}

function wholeDuration(string $value, int $minimum = 0): int
{
    if (!preg_match('/^[0-9]+$/D', $value) || (float)$value < $minimum || (float)$value > 240) {
        throw new InvalidArgumentException('Enter a whole number from ' . $minimum . ' to 240.');
    }
    return (int)$value;
}

function speakerSlotMinutes(string $value): int
{
    $minutes = durationMinutes($value);
    if ($minutes < 1) throw new InvalidArgumentException('Speaker time must be at least 1 minute.');
    return $minutes + (preg_match('/[-–—]/u', $value) ? 1 : 0);
}

function selectedBallots(array $meeting): array
{
    $all = array_keys(agendaBallots($meeting));
    $selected = $meeting['ballot_categories'] ?? json_decode($meeting['ballot_categories_json'] ?? 'null', true);
    if (is_array($selected)) return array_values(array_intersect($all, $selected));
    $defaults = ['Better Role Takers'];
    foreach (['Better Evaluator' => 'evaluators', 'Better Featured Speaker' => 'prepared_speakers', 'Better Table Topic Speaker' => 'tt_speakers'] as $label => $key) {
        $candidates = agendaBallots($meeting)[$label];
        if ($candidates && agendaSectionEnabled($meeting, $key)) $defaults[] = $label;
    }
    return $defaults;
}

function participantConflictErrors(array $meeting): array
{
    $seen = [];
    $errors = [];
    foreach (['prepared_speakers' => ['speaker_name', 'Prepared speaker'], 'tt_speakers' => ['speaker_name', 'TT speaker'], 'evaluators' => ['evaluator_name', 'Evaluator']] as $kind => [$field, $label]) {
        if (!agendaSectionEnabled($meeting, $kind)) continue;
        foreach (array_values($meeting[$kind] ?? []) as $index => $row) {
            $name = trim(preg_replace('/\s+/u', ' ', $row[$field] ?? ''));
            $key = mb_strtolower($name, 'UTF-8');
            if ($key === '' || ($kind === 'tt_speakers' && $key === 'given during the meeting')) continue;
            $position = $label . ' ' . ($index + 1);
            if (isset($seen[$key])) {
                [$previous, $previousKind] = $seen[$key];
                $crossRole = ($kind === 'evaluators') !== ($previousKind === 'evaluators');
                $errors[] = $name . ' is assigned to both ' . $previous . ' and ' . $position . '. ' . ($crossRole ? 'A person cannot be both speaker and evaluator.' : 'Choose a different person for each slot.');
            } else $seen[$key] = [$position, $kind];
        }
    }
    return $errors;
}

function clockTime(int $minutes): string
{
    $minutes = (($minutes % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function optionalAgendaKeys(): array
{
    return ['tmoe_featured_speaker_intro', 'prepared_speakers', 'topics_intro', 'tt_speakers', 'evaluation_break', 'evaluators_intro', 'evaluators', 'ballot_collection'];
}

function editableAgendaKeys(): array
{
    return ['tmoe_featured_speaker_intro', 'topics_intro', 'evaluation_break'];
}

function agendaOptions(array $meeting): array
{
    return $meeting['agenda_options'] ?? (json_decode($meeting['agenda_options_json'] ?? 'null', true) ?: []);
}

function agendaSectionEnabled(array $meeting, string $key): bool
{
    $options = agendaOptions($meeting);
    return !in_array($key, optionalAgendaKeys(), true) || !isset($options['included']) || in_array($key, $options['included'], true);
}

function buildAgenda(array $meeting, bool $includeExcluded = false): array
{

    $start = substr($meeting['start_time'] ?? '18:00', 0, 5);
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $start)) $start = '18:00';
    [$hours, $minutes] = array_map('intval', explode(':', $start));
    $cursor = $hours * 60 + $minutes;
    $initial = $cursor;
    $label = tmoeLabel($start);
    $blocks = [];
    foreach (agendaDefinitions($meeting['agenda_order'] ?? 'TT') as $definition) {
        [$key, $title, $role, $default] = $definition;
        $enabled = agendaSectionEnabled($meeting, $key);
        if (!$enabled && !$includeExcluded) continue;
        if (in_array($key, editableAgendaKeys(), true)) $title = agendaOptions($meeting)['titles'][$key] ?? $title;
        $template = $title;                                 // keep the raw TMoE version
        $title = preg_replace('/\bTMo[EMD]\b/', $label, $title); 
        $isGroup = ($definition[4] ?? '') === 'group';
        $rows = [];
        if ($isGroup) {
            $total = 0;
            foreach ($meeting[$key] ?? [] as $rowKey => $row) {
                $fallback = $key === 'evaluators' ? '2-3 mins' : '5-7 mins';
                $row['duration'] = $key === 'tt_speakers' ? '1-2 minutes' : (trim($row['duration'] ?? '') ?: $fallback);
                // Existing records without usable durations receive the documented default.
                try { $length = speakerSlotMinutes($row['duration']); }
                catch (InvalidArgumentException $e) { $row['duration'] = $fallback; $length = speakerSlotMinutes($fallback); }
                $row['time'] = clockTime($cursor + $total);
                $total += $length;
                $rows[$rowKey] = $row;
            }
            $duration = $total . ' mins';
        } else {
            $duration = $meeting['agenda_durations'][$key] ?? $default;
            $total = durationMinutes($duration);
        }
        if (!$enabled) $total = 0;
        $blocks[] = ['enabled' => $enabled, 'template' => $template, 'key' => $key, 'title' => $title, 'role' => $role, 'kind' => $isGroup ? 'group' : 'item',
            'person' => $meeting['roles'][$role] ?? '', 'duration' => $duration, 'minutes' => $total,
            'time' => clockTime($cursor), 'rows' => $rows];
        $cursor += $total;
    }
    return ['blocks' => $blocks, 'start' => clockTime($initial), 'end' => clockTime($cursor),
        'total' => $cursor - $initial, 'day_offset' => intdiv($cursor, 1440) - intdiv($initial, 1440)];
}

function agendaBallots(array $meeting): array
{
    $names = static fn(array $rows, string $field): array => array_values(array_filter(array_column($rows, $field), static fn($name) => trim($name) !== ''));
    $roles = $meeting['roles'] ?? [];
    return [
        'Better Role Takers' => ['Timer: ' . ($roles['timer'] ?? ''), 'Ah-Counter: ' . ($roles['ah_counter'] ?? ''), 'Grammarian: ' . ($roles['grammarian'] ?? '')],
        'Better Evaluator' => $names($meeting['evaluators'] ?? [], 'evaluator_name'),
        'Better Featured Speaker' => $names($meeting['prepared_speakers'] ?? [], 'speaker_name'),
        'Better Table Topic Speaker' => array_map(static fn($i) => 'TT Speaker ' . ($i + 1), array_keys(array_values($meeting['tt_speakers'] ?? []))),
    ];
}

function agendaEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Returns the time-appropriate TMo* label for the meeting's start time. */
function tmoeLabel(string $startTime): string
{
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $startTime, $m)) {
        return 'TMoE'; // fallback if time is missing/invalid
    }
    $minutes = ((int)$m[1]) * 60 + (int)$m[2];
    if ($minutes < 12 * 60) return 'TMoM';       // before 12 noon
    if ($minutes < 17 * 60) return 'TMoD';       // 12 noon – 5 pm
    return 'TMoE';                               // after 5 pm
}
