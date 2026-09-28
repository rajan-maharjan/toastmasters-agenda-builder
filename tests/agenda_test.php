<?php
require_once __DIR__ . '/../includes/agenda.php';

function expectSame($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

foreach (['1 min' => 1, '1-2 minutes' => 2, '5 - 7 mins' => 7, '2–3 mins' => 3, '0 mins' => 0, '12' => 12] as $input => $minutes) {
    expectSame(durationMinutes((string)$input), $minutes, 'Duration parsing');
}
foreach (['3-2 mins', 'abc', '2 hours', '-1 min', '241 mins', '2.5 mins'] as $input) {
    try { durationMinutes($input); }
    catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid duration accepted: ' . $input);
}

$meeting = ['start_time' => '18:00:00',
    'prepared_speakers' => [['speaker_name' => 'A', 'duration' => '4-6 mins'], ['speaker_name' => 'B', 'duration' => '5-7 mins'], ['speaker_name' => 'C', 'duration' => '8-10 mins']],
    'tt_speakers' => [['duration' => '8 mins'], ['duration' => '']],
    'evaluators' => [['duration' => '2-3 mins'], ['duration' => '']],
];
$agenda = buildAgenda($meeting);
expectSame($agenda['total'], 82, 'Each of seven participants receives exactly one extra minute');
expectSame($agenda['start'], '18:00', 'No seconds in displayed time');
expectSame($agenda['end'], '19:22', 'Calculated end');
$cursor = 18 * 60;
foreach ($agenda['blocks'] as $block) {
    expectSame($block['time'], clockTime($cursor), 'Every left-hand time follows the previous duration');
    $cursor += $block['minutes'];
    if ($block['key'] === 'tt_speakers') {
        expectSame($block['minutes'], 6, 'Table Topics use two speaking minutes plus one extra minute per speaker');
        expectSame($block['rows'][0]['duration'], '1-2 minutes', 'Table Topics displayed range');
    }
}
$meeting['agenda_durations']['saa_intro'] = '8-10 mins';
$changed = buildAgenda($meeting);
expectSame($changed['total'], 89, 'Changing a row changes the total by its duration delta');
expectSame($changed['blocks'][1]['time'], '18:10', 'Next row uses the edited upper bound');
$meeting['start_time'] = '23:50';
$overnight = buildAgenda($meeting);
expectSame($overnight['end'], '01:19', 'Midnight rollover');
expectSame($overnight['day_offset'], 1, 'Overnight date indicator');
expectSame(buildAgenda(['start_time' => '18:00'])['total'], 42, 'Empty participant groups do not add phantom durations');
expectSame(array_keys(agendaBallots([])), ['Better Role Takers', 'Better Evaluator', 'Better Featured Speaker', 'Better Table Topic Speaker'], 'Ballot category order');
echo "Agenda regression checks passed.\n";

foreach (['1-2' => 3, '5-7' => 8, '2-3' => 4, '7' => 7, '240' => 240, '7–9' => 10, '2—3' => 4, '6 mins' => 6] as $value => $minutes) {
    expectSame(speakerSlotMinutes((string)$value), $minutes, 'Only ranges add one minute');
}
expectSame(speakerSlotMinutes('7-9') + speakerSlotMinutes('6'), 16, 'User example: 10 plus 6');
expectSame(speakerSlotMinutes('2-3') + speakerSlotMinutes('5'), 9, 'User example: 4 plus 5');
foreach (['0', '7-5', '241', '2.5'] as $value) {
    try { speakerSlotMinutes($value); }
    catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid speaker duration accepted: ' . $value);
}
$rangeMeeting = ['start_time' => '18:00', 'prepared_speakers' => [['duration' => '5-7'], ['duration' => '7']]];
$rangeAgenda = buildAgenda($rangeMeeting);
foreach ($rangeAgenda['blocks'] as $block) {
    if ($block['key'] !== 'prepared_speakers') continue;
    expectSame($block['minutes'], 15, 'Only the range receives an extra minute');
    expectSame($block['rows'][1]['time'], clockTime((int)substr($block['time'], 0, 2) * 60 + (int)substr($block['time'], 3, 2) + 8), 'Next speaker starts after the buffered slot');
    $rangeMeeting['prepared_speakers'] = $block['rows'];
}
expectSame(buildAgenda($rangeMeeting)['total'], $rangeAgenda['total'], 'Rebuilding a saved agenda does not accumulate extra minutes');

$clubs = [
    ['name' => 'Nabil Toastmasters Club (07862550)', 'meeting_number' => '#154'],
    ['name' => 'Academia Toastmasters Club (07573029)', 'meeting_number' => '145'],
];
expectSame(agendaTitle(['clubs' => $clubs]), 'Agenda for Meeting #154 Nabil and #145 Academia', 'Combined club title');
$clubs[] = ['name' => 'Custom Club', 'meeting_number' => '7'];
expectSame(combinedClubTitle($clubs), '#154 Nabil, #145 Academia and #7 Custom Club', 'Three clubs in title');
expectSame(combinedClubTitle([['name' => 'Academia Toastmasters Club', 'meeting_number' => '']]), 'Academia', 'Optional meeting number');
expectSame(combinedClubTitle([]), '', 'Empty title');
expectSame(array_values(clubOfficerRoles()), ['President', 'Vice President Education', 'Vice President Membership', 'Vice President Public Relations', 'Secretary', 'Treasurer', 'Sergeant at Arms', 'Immediate Past President'], 'Fixed officer positions');
echo "Club title and officer role checks passed.\n";
expectSame(clubLocation([['area' => 'C3'], ['area' => 'C2'], ['area' => 'C1']]), ['area' => 'Area C3, C2, C1', 'division' => 'Division C'], 'Joint meeting areas');
expectSame(clubLocation([['area' => 'C1'], ['area' => 'C1'], ['area' => 'B2']]), ['area' => 'Area C1, B2', 'division' => 'Division C, B'], 'Distinct areas and first-character divisions');
expectSame(clubLocation([]), ['area' => '', 'division' => ''], 'Unselected club has no inferred location');
echo "Club area and division checks passed.\n";

expectSame(selectedBallots([]), array_keys(agendaBallots([])), 'All ballot categories selected by default');
expectSame(selectedBallots(['ballot_categories_json' => '[]']), [], 'All ballot categories can be excluded');
expectSame(selectedBallots(['ballot_categories_json' => '["Better Featured Speaker","Better Role Takers"]']), ['Better Role Takers', 'Better Featured Speaker'], 'Saved ballot selection retains display order');
expectSame(selectedBallots(['ballot_categories' => [], 'ballot_categories_json' => '["Better Role Takers"]']), [], 'Unchecked draft overrides saved choices');
expectSame(agendaBallots(['tt_speakers' => [3 => ['speaker_name' => 'Alice'], 8 => ['speaker_name' => 'Bob']]])['Better Table Topic Speaker'], ['TT Speaker 1', 'TT Speaker 2'], 'TT ballots use consecutive labels');
foreach (['0' => 0, '7' => 7, '240' => 240] as $value => $expected) {
    expectSame(wholeDuration((string)$value), $expected, 'Whole minute input');
}
foreach (['', '2.5', '-1', '241', '2 min', '5-7', '2e1', '+3'] as $value) {
    try { wholeDuration($value); } catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Invalid whole-minute duration accepted: ' . $value);
}
try { wholeDuration('0', 1); throw new RuntimeException('Zero participant duration accepted'); }
catch (InvalidArgumentException $e) {}
require_once __DIR__ . '/../includes/agenda_fields.php';
ob_start();
sheetDuration('speakers[0][duration]', '7-9', 'Speaker time', true, 1, false, true);
$speakerHtml = ob_get_clean();
expectSame(str_contains($speakerHtml, 'value="7-9"'), true, 'Editing preserves ranges and their scheduling rule');
ob_start();
sheetDuration('duration', '5-7 mins', 'Duration', true);
$html = ob_get_clean();
expectSame(str_contains($html, 'value="7"'), true, 'Existing duration ranges become numeric maximum');
expectSame(str_contains($html, 'class="duration-unit">min</span>'), true, 'Unit displayed outside input');
$durationDraft = true;
ob_start();
sheetDuration('duration', '2.5', 'Duration', true);
$html = ob_get_clean();
expectSame(str_contains($html, 'value="2.5"'), true, 'Rejected input retained for correction');
unset($durationDraft);
echo "Ballot selection and whole-minute validation checks passed.\n";
