<?php
// Run from the project root with agenda writes paused: php sql/migrate_member_references.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();
$migrationKey = 'agenda_member_reference_uses_primary_id';
if (!$pdo->query("SELECT GET_LOCK('agenda_member_reference_migration', 10)")->fetchColumn()) {
    throw new RuntimeException('Another member migration is running.');
}
try {
    $check = $pdo->prepare('SELECT setting_value FROM tiab_app_settings WHERE setting_key = ?');
    $check->execute([$migrationKey]);
    if ($check->fetchColumn() === '1') {
        echo "Member reference migration already applied.\n";
    } else {
        // Refuse missing or ambiguous legacy references rather than attach an agenda to another person.
        $invalid = $pdo->query("SELECT r.id FROM tiab_functional_roles r
            LEFT JOIN tin_members m ON m.member_id = r.member_id
            WHERE r.member_id IS NOT NULL AND r.member_id <> ''
            GROUP BY r.id HAVING COUNT(m.id) <> 1 LIMIT 1")->fetchColumn();
        if ($invalid !== false) throw new RuntimeException('Resolve missing or duplicate legacy member references before migrating. Role row: ' . $invalid);

        // DDL commits implicitly in MySQL; run it before the data transaction. It is safe to retry.
        $pdo->exec("ALTER TABLE tin_members MODIFY gender ENUM('M','F') NULL DEFAULT NULL");
        $pdo->beginTransaction();
        $count = $pdo->exec("UPDATE tiab_functional_roles r
            JOIN tin_members m ON m.member_id = r.member_id
            SET r.member_id = CAST(m.id AS CHAR)
            WHERE r.member_id IS NOT NULL AND r.member_id <> ''");
        $pdo->prepare('INSERT INTO tiab_app_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$migrationKey, '1']);
        $pdo->commit();
        echo "Migrated $count agenda member references; gender is now optional.\n";
    }
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->query("SELECT RELEASE_LOCK('agenda_member_reference_migration')");
}
