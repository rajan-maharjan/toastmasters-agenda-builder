<?php
require_once __DIR__ . '/includes/security.php';
requirePostAndCsrf();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = requestId($_POST, 'id');

if ($id && $id > 0) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM tiab_meetings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        securityAudit('agenda_deleted', $id);
        
        $_SESSION['success'] = "Agenda deleted successfully.";
    } catch (PDOException $e) {
        error_log((string)$e);
        $_SESSION['error'] = 'Could not delete the agenda. Please try again later.';
    }
} else {
    $_SESSION['error'] = "Invalid meeting ID.";
}

header("Location: index.php");
exit;
