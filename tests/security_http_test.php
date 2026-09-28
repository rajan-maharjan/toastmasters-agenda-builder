<?php
// Starts an isolated HTTP server and creates/updates/deletes only its own marked test agenda.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$password = bin2hex(random_bytes(24));
$env = getenv();
$env['AGENDA_ORGANIZER_PASSWORD_HASH'] = password_hash($password, PASSWORD_DEFAULT);
$temporary = sys_get_temp_dir() . '/agenda-http-' . bin2hex(random_bytes(16));
mkdir($temporary, 0700);
$env['TMP'] = $env['TEMP'] = $env['TMPDIR'] = $temporary;
$log = $temporary . '/server.log';
$process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temporary, '-S', $address, '-t', $root], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
if (!is_resource($process)) throw new RuntimeException('Cannot start test server');
$cookie = '';
$createdId = null;
$csrf = null;
function httpCheck(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
function request(string $path, ?array $data = null): array
{
    global $address, $cookie;
    $options = ['method' => $data === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        'header' => "Cookie: $cookie\r\n"];
    if ($data !== null) {
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = http_build_query($data);
    }
    $body = file_get_contents('http://' . $address . '/' . $path, false, stream_context_create(['http' => $options]));
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) $cookie = $match[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return [(int)$status[1], (string)$body, implode("\n", $http_response_header)];
}
try {
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    $anonymous = request('form.php');
    httpCheck($anonymous[0] === 200 && str_contains($anonymous[1], 'id="agenda-form"'), 'Public agenda creation unavailable');
    httpCheck(str_contains($anonymous[2], 'HttpOnly') && str_contains($anonymous[2], 'SameSite=Lax'), 'Session cookie flags missing');
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $anonymous[1], $matches);
    $publicCsrf = $matches[1];
    httpCheck(request('new_agenda.php')[0] === 302, 'Legacy public creation link unavailable');
    httpCheck(request('save.php', [])[0] === 403, 'Save without CSRF accepted');
    httpCheck(request('save.php', ['csrf_token' => $publicCsrf])[0] === 302, 'Public creation did not reach form validation');
    httpCheck(request('save.php', ['csrf_token' => $publicCsrf, 'meeting_id' => '1'])[0] === 302, 'Public update did not reach validation');
    httpCheck(request('save.php', ['csrf_token' => $publicCsrf, 'meeting_id' => '0'])[0] === 400, 'Invalid ID bypassed update protection');
    httpCheck(request('delete.php', ['id' => '1'])[0] === 403, 'Anonymous deletion accepted');
    [$status, $body, $headers] = request('form.php');
    httpCheck($status === 200, 'Public form unavailable');
    httpCheck(str_contains($headers, "script-src 'self'") && str_contains($headers, 'X-Frame-Options: DENY'), 'Security headers missing');
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $body, $matches);
    $csrf = $matches[1];
    httpCheck(request('delete.php')[0] === 405, 'GET deletion accepted');
    httpCheck(request('delete.php', ['csrf_token' => $csrf, 'id' => ['1']])[0] === 400, 'Malformed delete ID accepted');
    httpCheck(request('save.php', ['csrf_token' => $csrf, 'theme' => ['bad']])[0] === 400, 'Malformed agenda accepted');
    httpCheck(request('view.php?id[]=1')[0] === 400, 'Malformed public ID accepted');
    httpCheck(request('generate_pdf.php?id=1%20OR%201=1')[0] === 400, 'Invalid PDF ID accepted');
    foreach (["1 OR 1=1", "1; SELECT 1", '../../config/db.php', "1' UNION SELECT 1--"] as $badId) {
        httpCheck(request('form.php?id=' . rawurlencode($badId))[0] === 400, 'Injection/traversal ID accepted');
        httpCheck(request('delete.php', ['csrf_token' => $csrf, 'id' => $badId])[0] === 400, 'Delete injection accepted');
    }
    preg_match('/<option value="([0-9]+)" data-officers=/', $body, $club);
    httpCheck(isset($club[1]), 'Test requires an active club');
    $marker = 'Security regression ' . bin2hex(random_bytes(8));
    $payload = $marker . ' <script>alert(1)</script> \' OR 1=1 --';
    $agenda = ['csrf_token' => $csrf, 'district' => 'District 41', 'division' => 'Division C', 'area' => 'Area C1',
        'theme' => $payload, 'meeting_date' => date('Y-m-d'), 'start_time' => '18:15', 'timezone' => 'NPT',
        'quote_text' => 'Knowing is not enough; we must apply.', 'quote_author' => 'Johann Wolfgang von Goethe',
        'mission' => 'We provide a supportive and positive learning experience.', 'venue_details' => 'Meeting Hall, Kathmandu',
        'wod_word' => 'Savor', 'wod_meaning' => 'To enjoy a pleasant experience slowly and completely.',
        'wod_synonyms' => 'Relish, Enjoy, Taste', 'wod_example' => 'They wanted to savor their victory.',
        'clubs' => [['club_id' => $club[1], 'meeting_number' => 'security-test']],
        'speakers' => [3 => ['speaker_name' => 'First Speaker', 'topic' => 'The Power to Pause', 'duration' => '7-9'],
            8 => ['speaker_name' => 'Second Speaker', 'topic' => 'You matter to me', 'duration' => '6']],
        'evaluators' => [2 => ['evaluator_name' => 'First Evaluator', 'speaker_index' => '3', 'duration' => '2-3'],
            5 => ['evaluator_name' => 'Second Evaluator', 'speaker_index' => '8', 'duration' => '5']],
        'tt_speakers' => array_fill(0, 3, ['speaker_name' => '', 'duration' => '1-2'])];
    preg_match_all('/<option value="([0-9]+)" data-officers=/', $body, $clubOptions);
    $clubIds = array_slice(array_values(array_unique($clubOptions[1])), 0, 4);
    $agenda['clubs'] = [];
    foreach ($clubIds as $index => $clubId) {
        $agenda['clubs'][] = ['club_id' => $clubId, 'meeting_number' => (string)(154 + $index),
            'officers' => array_fill_keys(['president','vpe','vpm','vppr','secretary','treasurer','saa','ipp'], 'Officer ' . ($index + 1))];
    }
    $saved = request('save.php', $agenda);
    preg_match('/Location: view\.php\?id=(\d+)&saved=1/i', $saved[2], $location);
    httpCheck($saved[0] === 302 && isset($location[1]), 'Public create failed');
    $createdId = $location[1];
    $preview = request('view.php?id=' . $createdId);
    httpCheck($preview[0] === 200 && str_contains($preview[1], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($preview[1], '<script>alert(1)</script>'), 'Stored XSS was not escaped');
    $edit = request('form.php?id=' . $createdId);
    httpCheck($edit[0] === 200, 'Public editing unavailable');
    httpCheck(str_contains($edit[1], 'value="7-9"') && str_contains($edit[1], 'value="6"'), 'Speaker ranges changed on reload');
    httpCheck(str_contains($edit[1], 'name="quote_author"') && str_contains($preview[1], 'Johann Wolfgang von Goethe'), 'Quote attribution is lost in editor/preview');
    httpCheck(str_contains($preview[1], '>19:31</output>'), 'Mixed speaker ranges/integers changed the end time');
    $invalid = $agenda;
    $invalid['meeting_id'] = $createdId;
    $invalid['start_time'] = '25:61';
    $invalid['speakers'][3]['duration'] = '9-7';
    request('save.php', $invalid);
    $newForm = request('form.php');
    httpCheck(!str_contains($newForm[1], $marker) && !str_contains($newForm[1], 'value="9-7"'), 'Failed edit contaminated a new agenda');
    $draft = request('form.php?id=' . $createdId);
    httpCheck(str_contains($draft[1], 'value="25:61"') && str_contains($draft[1], 'value="9-7"'), 'Rejected draft values were silently replaced');
    $agenda['meeting_id'] = $createdId;
    $agenda['theme'] = $marker . ' edited';
    httpCheck(request('save.php', $agenda)[0] === 302, 'Public update failed');
    httpCheck(str_contains(request('view.php?id=' . $createdId)[1], $marker . ' edited'), 'Update was not persisted');
    $listing = request('index.php');
    httpCheck(str_contains($listing[1], '18:15 - 19:31'), 'List schedule differs from preview');
    $pdf = request('generate_pdf.php?id=' . $createdId);
    httpCheck($pdf[0] === 200 && str_starts_with($pdf[1], '%PDF-'), 'Public PDF unavailable');
    if ($artifactDir = getenv('AGENDA_TEST_ARTIFACT_DIR')) {
        if (!is_dir($artifactDir)) mkdir($artifactDir, 0700, true);
        file_put_contents($artifactDir . '/regression-agenda.pdf', $pdf[1]);
    }
    $agenda['ballot_selection_present'] = '1';
    $agenda['ballot_categories'] = [];
    $agenda['start_time'] = '23:50';
    request('save.php', $agenda);
    $overnight = request('view.php?id=' . $createdId);
    httpCheck(str_contains($overnight[1], '>01:06</output>') && str_contains($overnight[1], '(+1 day)'), 'Overnight schedule regressed');
    httpCheck(str_contains(request('index.php')[1], '23:50 - 01:06 (+1 day)'), 'List omitted overnight day offset');
    httpCheck(!str_contains(request('form.php?id=' . $createdId)[1], 'checked'), 'Unchecked ballot categories were restored');
    httpCheck(request('delete.php', ['csrf_token' => $csrf, 'id' => $createdId])[0] === 302, 'Public delete failed');
    httpCheck(request('view.php?id=' . $createdId)[0] === 302, 'Deleted agenda still exists');
    $createdId = null;
    echo "HTTP security checks passed: public create/edit/delete/view/PDF, CSRF, headers, SQL payloads, stored XSS, malformed IDs.\n";
} finally {
    if ($createdId !== null && $csrf !== null) request('delete.php', ['csrf_token' => $csrf, 'id' => $createdId]);
    fclose($pipes[0]);
    proc_terminate($process);
    proc_close($process);
    foreach (scandir($temporary) as $file) {
        if ($file !== '.' && $file !== '..' && is_file($temporary . '/' . $file)) unlink($temporary . '/' . $file);
    }
    rmdir($temporary);
}
