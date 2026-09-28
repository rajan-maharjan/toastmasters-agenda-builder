<?php
// Run once using an existing administrative connection. Does not alter agenda data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/db.php';
$path = __DIR__ . '/../config/database.local.php';
if (is_file($path)) { fwrite(STDERR, "Local database configuration already exists; review it manually.\n"); exit(1); }
$pdo = getDB();
$user = 'tiagenda_' . bin2hex(random_bytes(4));
$password = bin2hex(random_bytes(32));
$account = $pdo->quote($user) . "@'localhost'";
$database = '`' . str_replace('`', '``', DB_NAME) . '`';
$readTables = ['tin_clubs', 'tin_fy_club_detail', 'tin_members', 'tin_members_club_role', 'tiab_app_settings'];
$writeTables = ['tiab_meetings', 'tiab_officers', 'tiab_functional_roles', 'tiab_tt_speakers', 'tiab_prepared_speakers',
    'tiab_evaluators', 'tiab_agenda_durations', 'tiab_meeting_clubs'];
$created = false;
try {
    // Check required tables before creating any account.
    foreach (array_merge($readTables, $writeTables) as $table) $pdo->query("SELECT 1 FROM $database.`$table` LIMIT 0");
    $pdo->exec('CREATE USER ' . $account . ' IDENTIFIED BY ' . $pdo->quote($password));
    $created = true;
    foreach ($readTables as $table) $pdo->exec("GRANT SELECT ON $database.`$table` TO $account");
    foreach ($writeTables as $table) $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $database.`$table` TO $account");
    $verify = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $verify->query('SELECT id FROM tiab_meetings LIMIT 0');
    $config = ['host' => DB_HOST, 'name' => DB_NAME, 'user' => $user, 'password' => $password];
    if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) throw new RuntimeException('Cannot write local configuration');
    chmod($path, 0600);
    echo "Dedicated database account configured and connection verified. No agenda data changed.\n";
} catch (Throwable $error) {
    if ($created) $pdo->exec('DROP USER ' . $account);
    // SQL exceptions can contain the password-bearing CREATE USER statement.
    fwrite(STDERR, "Database account setup failed; no new account retained. Check database permissions and required tables.\n");
    exit(1);
}
