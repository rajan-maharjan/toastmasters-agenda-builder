<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
require_once __DIR__ . '/vendor/autoload.php';
$meeting = getMeetingFull(requestId($_GET, 'id'));
if (!$meeting) { header('Location: index.php'); exit; }
$schedule = buildAgenda($meeting);

class AgendaPDF extends TCPDF
{
    public function Header() {}
    public function Footer()
    {
        $this->SetXY(8, -8);
        $this->SetFont('dejavusans', 'U', 7);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(155, 4, 'Generated from: https://rajanmaharjan.com.np', 0, 0, 'L', false, 'https://rajanmaharjan.com.np');
        $this->SetFont('dejavusans', '', 6);
        $this->Cell(39, 4, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
    public function room(float $height): bool
    {
        if ($this->GetY() + $height <= 284) return false;
        if ($this->getPage() < $this->getNumPages()) { $this->setPage($this->getPage() + 1); }
        else { $this->AddPage(); }
        $this->SetY(10);
        return true;
    }
    public function rowHeight(array $values, array $widths, float $size = 7.4, bool $bold = false): float
    {
        $this->SetFont('dejavusans', $bold ? 'B' : '', $size);
        $height = 5.2;
        foreach ($values as $i => $value) { $height = max($height, $this->getStringHeight($widths[$i], (string)$value)); }
        return $height;
    }
    public function wordOfTheDay(array $meeting): void
    {
        $rows = [];
        foreach (['wod_word' => 'Word of day', 'wod_meaning' => 'Meaning', 'wod_synonyms' => 'Synonyms', 'wod_example' => 'Example'] as $field => $label) {
            $values = [$label, trim($meeting[$field] ?? '')];
            $minimum = $field === 'wod_example' ? 10 : ($field === 'wod_meaning' ? 6.4 : 5.2);
            $rows[] = [$values, max($minimum, $this->rowHeight($values, [36,102]))];
        }
        $this->room(array_sum(array_column($rows, 1)));
        $this->SetFont('dejavusans', '', 7.4);
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        foreach ($rows as [$values, $height]) {
            $this->room($height);
            $y = $this->GetY();
            // Align beneath the agenda item, leaving the time column clear.
            $this->SetFillColor(243, 243, 243);
            $this->MultiCell(36, $height, $values[0], 1, 'L', true, 0, 64, $y, true, 0, false, true, $height, 'M');
            $this->SetFillColor(255, 255, 255);
            $this->MultiCell(102, $height, $values[1], 1, 'L', true, 0, 100, $y, true, 0, false, true, $height, 'M');
            $this->SetY($y + $height);
        }
    }
    public function row(array $values, array $widths, float $x = 52, array $fill = [255,255,255], bool $bold = false, float $size = 7.4, array $color = [0,0,0], bool $border = true): void
    {
        $height = $this->rowHeight($values, $widths, $size, $bold);
        $this->room($height);
        $this->SetFont('dejavusans', $bold ? 'B' : '', $size);
        $y = $this->GetY();
        $this->SetTextColor(...$color);
        $this->SetFillColor(...$fill);
        $this->SetDrawColor(0, 0, 0);
        foreach ($values as $i => $value) {
            $this->MultiCell($widths[$i], $height, (string)$value, $border ? 1 : 0, 'L', true, 0, $x, $y, true, 0, false, true, $height, 'M');
            $x += $widths[$i];
        }
        $this->SetY($y + $height);
    }
}
$pdf = new AgendaPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('https://rajanmaharjan.com.np');
$pdf->SetAuthor('Rajan Maharjan');
$pdf->SetTitle('Meeting Agenda - ' . $meeting['theme']);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(false, 8);
$pdf->setCellPaddings(1.2, 0.8, 1.2, 0.8);
$pdf->SetLineWidth(0.15);
$pdf->AddPage();
$meta = implode(' | ', array_filter([$meeting['district'], $meeting['division'], $meeting['area']]));
$range = $schedule['start'] . ' - ' . $schedule['end'] . ($schedule['day_offset'] ? ' (+' . $schedule['day_offset'] . ' day)' : '') . ' ' . $meeting['timezone'];
$logoFile = __DIR__ . '/assets/img/toastmasters_logo_bw.png';
$pdf->Image($logoFile, 8, 8, 32, 0, 'PNG');
$pdf->SetY(8);
// Center the heading hierarchy beside the logo, with natural wrapping for long titles.
$pdf->SetTextColor(0, 0, 0);
foreach ([[$meta, 13, 'BU'], [agendaTitle($meeting), 11, 'B'], ['Theme: ' . $meeting['theme'], 10, 'B']] as [$text, $size, $style]) {
    $pdf->SetFont('dejavusans', $style, $size);
    $height = max(6, $pdf->getStringHeight(156, $text));
    $pdf->MultiCell(156, $height, $text, 0, 'C', false, 1, 46, $pdf->GetY());
    $pdf->Ln(1);
}
// Place the date and time at the right, immediately above the schedule.
$pdf->SetY(max(30, $pdf->GetY() + 1));
$pdf->SetFont('dejavusans', 'B', 8);
$dateTime = date('d M Y', strtotime($meeting['meeting_date'])) . ' | ' . $range;
$dateWidth = min(150, max(75, $pdf->GetStringWidth($dateTime) + 8));
$dateHeight = max(6, $pdf->getStringHeight($dateWidth, $dateTime));
$pdf->SetDrawColor(0, 0, 0);
$pdf->MultiCell($dateWidth, $dateHeight, $dateTime, 0, 'R', false, 1, 202 - $dateWidth, $pdf->GetY());
$bodyY = $pdf->GetY() + 3;
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(8, $bodyY - 1.5, 202, $bodyY - 1.5);

// Officers and supporting information occupy the left column.
$pdf->SetY($bodyY);
$pdf->row(['CLUB OFFICERS'], [41], 8, [255,255,255], true, 8, [0,0,0], false);
foreach (clubOfficerRoles() as $role => $label) {
    $pdf->room(11);
    $pdf->row([$label], [41], 8, [255,255,255], true, 7, [0,0,0], false);
    foreach ($meeting['clubs'] ?? [] as $club) {
        $name = $club['officers'][$role] ?? '';
        $line = (count($meeting['clubs']) > 1 ? shortClubName($club['name']) . ': ' : '') . ($name !== '' ? $name : '________________');
        $pdf->row([$line], [41], 8, [255,255,255], false, 7, [0,0,0], false);
    }
}
foreach (['mission' => 'OUR MISSION', 'quote_text' => 'QUOTE', 'venue_details' => 'MEETING DETAILS'] as $key => $label) {
    if (trim($meeting[$key] ?? '') === '') continue;
    $pdf->Ln(3);
    $pdf->row([$label], [41], 8, [255,255,255], true, 7, [0,0,0], false);
    $pdf->row([$meeting[$key]], [41], 8, [255,255,255], false, 7, [0,0,0], false);
    if ($key === 'quote_text' && trim($meeting['quote_author'] ?? '') !== '') {
        $pdf->row(['— ' . $meeting['quote_author']], [41], 8, [255,255,255], false, 7, [0,0,0], false);
    }
}
$pdf->Ln(3);
$pdf->row(['www.toastmasters.org'], [41], 8, [255,255,255], false, 6.8, [0,0,0], false);

$pdf->setPage(1);
$pdf->SetY($bodyY);
$durationWidth = 19;
$widths = [12, 83, 36, $durationWidth];
$pdf->row(['TIME', 'AGENDA ITEM', 'ROLE TAKER', 'DURATION'], $widths, 52, [255,255,255], true, 6.8, [0,0,0]);
foreach ($schedule['blocks'] as $block) {
    if ($block['kind'] === 'item') {
        if (in_array($block['key'], ['tmoe_featured_speaker_intro', 'topics_intro', 'evaluators_intro', 'conclusion'], true)) {
            $pdf->room(5.2 + $pdf->rowHeight([$block['time'], $block['title'], $block['person'], $block['duration']], $widths));
            $pdf->Ln(5.2);
        }
        $pdf->row([$block['time'], $block['title'], $block['person'], (string)$block['minutes'].' min'], $widths);
        if ($block['key'] === 'grammarian_intro') {
            $pdf->wordOfTheDay($meeting);
        }
        continue;
    }
    $key = $block['key'];
    // Inset participant tables beneath the agenda item, clear of the time column.
    $tableX = 64;
    // Every duration cell spans x=183 to x=202, including inset tables.
    if ($key === 'prepared_speakers') { $heads = ['Speaker', 'Topic', 'Level', 'Pathways', 'Time']; $cols = [32,42,14,31,$durationWidth]; }
    elseif ($key === 'evaluators') { $heads = ['Evaluator', 'Speaker', 'Time']; $cols = [59.5,59.5,$durationWidth]; }
    else { $heads = ['Table Topic Speaker', 'Title of Speech', 'Time']; $cols = [34,85,$durationWidth]; }
    $pdf->room(17);
    $pdf->row([$block['time'], $block['title'], (string)$block['minutes'].' min'], [12,119,$durationWidth], 52, [255,255,255], true, 7.4, [0,0,0]);
    $pdf->row($heads, $cols, $tableX, [255,255,255], true, 7);
    foreach (array_values($block['rows']) as $i => $row) {
        $speakingTime = trim(preg_replace('/\s*(?:minutes|minute|mins|min|m)\s*$/iu', '', $row['duration'])) . ' min';
        if ($key === 'prepared_speakers') { $values = [$row['speaker_name'], $row['topic'], $row['level'], $row['pathways'], $speakingTime]; }
        elseif ($key === 'evaluators') { $values = [$row['evaluator_name'], $row['speaker_name'], $speakingTime]; }
        else { $values = ['TT Speaker ' . ($i + 1), $row['speaker_name'], $speakingTime]; }
        if ($pdf->room($pdf->rowHeight($values, $cols))) { $pdf->row($heads, $cols, $tableX, [255,255,255], true, 7); }
        $pdf->row($values, $cols, $tableX);
    }
}

// Selected ballot categories share one row in the configured order.
$ballots = array_intersect_key(agendaBallots($meeting), array_flip(selectedBallots($meeting)));
if ($ballots) {
$boxWidth = (150 - (count($ballots) - 1) * 1.33) / count($ballots);
$pdf->SetFont('dejavusans', '', 6.8);
$ballotHeight = 0;
$ballotTexts = [];
foreach ($ballots as $label => $candidates) {
    $text = implode("\n", array_map(static fn($candidate) => '[ ] ' . $candidate, $candidates));
    $ballotTexts[] = $text;
    $ballotHeight = max($ballotHeight, $pdf->getStringHeight($boxWidth, $text));
}
$pdf->SetFont('dejavusans', 'B', 6.8);
$headingHeight = max(array_map(fn($label) => $pdf->getStringHeight($boxWidth, $label), array_keys($ballots)));
$pdf->room($headingHeight + $ballotHeight + 10);
$pdf->Ln(3);
$pdf->row(['BALLOT SHEET - Choose one in each category'], [150], 52, [255,255,255], true, 7.2, [0,0,0], false);
$ballotY = $pdf->GetY();
foreach (array_keys($ballots) as $i => $label) {
    $x = 52 + $i * ($boxWidth + 1.33);
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetFillColor(255,255,255);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('dejavusans', 'B', 6.8);
    $pdf->MultiCell($boxWidth, $headingHeight, $label, 1, 'L', true, 0, $x, $ballotY, true, 0, false, true, $headingHeight, 'M');
    $pdf->SetFillColor(255,255,255);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('dejavusans', '', 6.8);
    $pdf->MultiCell($boxWidth, $ballotHeight, $ballotTexts[$i], 1, 'L', true, 0, $x, $ballotY + $headingHeight, true, 0, false, true, $ballotHeight, 'T');
}
}
$pdf->Output('Agenda_' . $meeting['meeting_date'] . '_' . preg_replace('/[^a-z0-9]/i', '_', $meeting['theme']) . '.pdf', 'D');
exit;
