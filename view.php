<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/agenda.php';
$meeting = getMeetingFull(requestId($_GET, 'id'));
if (!$meeting) { $_SESSION['error'] = 'Meeting not found.'; header('Location: index.php'); exit; }
$editable = false;
$pageTitle = 'Agenda Preview';
require __DIR__ . '/includes/header.php';
?>
<div class="builder-toolbar no-print">
    <div><h1>Agenda Preview</h1><p>Your meeting schedule and ballot sheet, ready to share.</p></div>
    <div class="builder-actions">
        <a href="form.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-outline-primary">&lt;&lt; Go Back & Edit</a>
        <a href="generate_pdf.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-primary">Download PDF</a>
        <a href="form.php" class="btn btn-danger">Create New Agenda</a>
    </div>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success no-print">Agenda saved successfully.</div><?php endif; ?>
<?php require __DIR__ . '/includes/agenda_sheet.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
