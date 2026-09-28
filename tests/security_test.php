<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/agenda.php';

function check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}

$_SESSION = [];
$token = csrfToken();
check(strlen($token) === 64 && csrfToken() === $token, 'CSRF token must be random and session-stable');
check(validCsrfToken($token), 'Valid CSRF rejected');
foreach ([null, '', [], str_repeat('0', 64)] as $bad) check(!validCsrfToken($bad), 'Invalid CSRF accepted');
$_SESSION = [];
check(!validCsrfToken($token), 'Token from a different session accepted');

$hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
putenv('AGENDA_ORGANIZER_PASSWORD_HASH=' . $hash);
check(!isOrganizer(), 'Anonymous visitor authenticated');
$_SESSION = ['organizer' => hash('sha256', $hash), 'last_activity' => time(), 'signed_in_at' => time()];
check(isOrganizer(), 'Valid organizer rejected');
$_SESSION['last_activity'] = time() - 1801;
check(!isOrganizer(), 'Idle session accepted');
$_SESSION = ['organizer' => hash('sha256', $hash), 'last_activity' => time(), 'signed_in_at' => time() - 28801];
check(!isOrganizer(), 'Expired session accepted');
$_SESSION = ['organizer' => hash('sha256', $hash), 'last_activity' => time(), 'signed_in_at' => time()];
putenv('AGENDA_ORGANIZER_PASSWORD_HASH=' . password_hash('rotated-test-password', PASSWORD_DEFAULT));
check(!isOrganizer(), 'Password change did not revoke the session');

validateAgendaInput(['theme' => '<script>alert(1)</script>', 'clubs' => [4 => ['club_id' => '1', 'officers' => ['president' => 'Name']]],
    'speakers' => [7 => ['speaker_name' => 'Name', 'duration' => '7']], 'evaluators' => [['speaker_index' => '7', 'duration' => '3']]]);
foreach ([
    ['theme' => []], ['theme' => str_repeat('x', 501)], ['meeting_date' => "2026\0-01-01"],
    ['clubs' => 'invalid'], ['clubs' => ['bad-key' => []]], ['clubs' => [['officers' => ['president' => []]]]],
    ['speakers' => [null]], ['speakers' => array_fill(0, 51, [])], ['evaluators' => [['speaker_index' => []]]],
    ['roles' => ['timer' => []]], ['ballot_categories' => [[]]], ['organizer' => 'true'], ['theme' => "\xFF"],
] as $input) {
    try { validateAgendaInput($input); }
    catch (InvalidArgumentException $e) { continue; }
    throw new RuntimeException('Malformed request accepted: ' . var_export($input, true));
}
check(agendaEscape('<script>"&\'') === '&lt;script&gt;&quot;&amp;&#039;', 'HTML escaping failed');
$address = 'test-' . bin2hex(random_bytes(16));
$limiterPath = sys_get_temp_dir() . '/tiagenda-login-' . hash('sha256', realpath(__DIR__ . '/../includes') . '|' . $address) . '.json';
try {
    for ($i = 0; $i < 10; $i++) check(consumeLoginAttempt($address), 'Rate limited too early');
    $_SESSION = [];
    check(!consumeLoginAttempt($address), 'New session bypassed login throttle');
} finally { if (is_file($limiterPath)) unlink($limiterPath); }
echo "Security checks passed: CSRF, organizer access, session expiry/revocation, input validation, escaping, login throttling.\n";
