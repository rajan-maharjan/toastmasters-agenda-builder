<?php
// Shared security boundary. Include before output on every HTTP entry point.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function securityReject(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function secureRequest(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
}

function securityAudit(string $event, ?int $agendaId = null): void
{
    error_log('agenda_security ' . json_encode(['event' => $event, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'agenda_id' => $agendaId, 'time' => gmdate('c')], JSON_INVALID_UTF8_SUBSTITUTE));
}

function organizerPasswordHash(): string
{
    $hash = getenv('AGENDA_ORGANIZER_PASSWORD_HASH');
    if ($hash !== false && $hash !== '') return $hash;
    $path = __DIR__ . '/../config/organizer.local.php';
    return is_file($path) ? (string)require $path : '';
}

function isOrganizer(): bool
{
    $hash = organizerPasswordHash();
    $valid = $hash !== '' && isset($_SESSION['organizer'], $_SESSION['last_activity'], $_SESSION['signed_in_at'])
        && is_string($_SESSION['organizer'])
        && hash_equals(hash('sha256', $hash), $_SESSION['organizer'])
        && time() - $_SESSION['last_activity'] < 1800
        && time() - $_SESSION['signed_in_at'] < 28800;
    if ($valid) $_SESSION['last_activity'] = time();
    else unset($_SESSION['organizer'], $_SESSION['last_activity'], $_SESSION['signed_in_at']);
    return $valid;
}

function requireOrganizer(): void
{
    if (isOrganizer()) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        header('Location: login.php', true, 303);
        exit;
    }
    securityAudit('unauthorized_write');
    securityReject(403, 'Organizer sign-in required.');
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validCsrfToken($token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requirePostAndCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        securityReject(405, 'POST required.');
    }
    if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
        securityAudit('csrf_rejected');
        securityReject(403, 'Form expired or invalid. Reload the page and try again.');
    }
    if (!empty($_FILES)) securityReject(400, 'File uploads are not supported.');
}

function requestId(array $input, string $key, bool $optional = false): ?int
{
    if (!isset($input[$key]) && $optional) return null;
    $value = $input[$key] ?? null;
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (float)$value > 4294967295) {
        securityReject(400, 'Invalid agenda ID.');
    }
    return (int)$value;
}

// Persistent, locked counters prevent bypass by discarding the session cookie.
function consumeLoginAttempt(string $address): bool
{
    $path = sys_get_temp_dir() . '/tiagenda-login-' . hash('sha256', __DIR__ . '|' . $address) . '.json';
    $file = fopen($path, 'c+');
    if (!$file || !flock($file, LOCK_EX)) throw new RuntimeException('Login rate limiter unavailable');
    try {
        $state = json_decode(stream_get_contents($file), true);
        if (!is_array($state) || ($state['until'] ?? 0) <= time()) $state = ['until' => time() + 900, 'count' => 0];
        if ($state['count'] >= 10) return false;
        $state['count']++;
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, json_encode($state, JSON_THROW_ON_ERROR)) === false || !fflush($file)) {
            throw new RuntimeException('Login rate limiter write failed');
        }
        return true;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $error): void {
        error_log((string)$error);
        securityReject(500, 'The request could not be completed. Please try again later.');
    });
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store');
    if (secureRequest()) header('Strict-Transport-Security: max-age=31536000');
    session_name('tiagenda_session');
    $cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    $sessionStarted = session_start([
        'use_strict_mode' => 1, 'use_only_cookies' => 1, 'use_trans_sid' => 0,
        'cookie_httponly' => 1, 'cookie_secure' => secureRequest(), 'cookie_samesite' => 'Lax',
        'cookie_path' => $cookiePath,
    ]);
    if (!$sessionStarted) securityReject(503, 'Session storage is unavailable. Please try again later.');
    // Do not transmit organizer credentials or sessions over remote cleartext HTTP.
    if (!secureRequest() && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        unset($_SESSION['organizer']);
    }
}
