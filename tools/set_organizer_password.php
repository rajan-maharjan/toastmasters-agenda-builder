<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$password = rtrim(stream_get_contents(STDIN, 4096), "\r\n");
if (strlen($password) < 15 || strlen($password) > 72) {
    fwrite(STDERR, "Use a password between 15 and 72 bytes.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$path = __DIR__ . '/../config/organizer.local.php';
if (file_put_contents($path, "<?php\nreturn " . var_export($hash, true) . ";\n", LOCK_EX) === false) exit(1);
chmod($path, 0600);
echo "Organizer password configured. Existing organizer sessions are invalidated.\n";
