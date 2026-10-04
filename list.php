<?php
require_once __DIR__ . '/includes/security.php';
require_once 'config/db.php';
require_once __DIR__ . '/includes/agenda.php';
$meetings = getMeetingSummaries();

$pageTitle = 'Saved Agendas';
require 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Meeting Agendas</h2>
        <a href="form.php" class="btn btn-primary">Create New Agenda</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (empty($meetings)): ?>
        <div class="alert alert-info">No agendas yet. Create your first one!</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover border">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Meeting Number</th>
                        <th>Theme</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $index => $meeting): ?>
                        <?php $schedule = buildAgenda($meeting); ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($meeting['meeting_number']) ?></td>
                            <td><?= htmlspecialchars($meeting['theme']) ?></td>
                            <td><?= date('D, d M Y', strtotime($meeting['meeting_date'])) ?></td>
                            <td><?= $schedule['start'] ?> - <?= $schedule['end'] ?><?= $schedule['day_offset'] ? ' (+' . $schedule['day_offset'] . ' day)' : '' ?> <?= htmlspecialchars($meeting['timezone'] ?? '') ?></td>
                            <td>
                                <a href="view.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-sm btn-info text-white" title="View">View</a>
                                <a href="generate_pdf.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-sm btn-success">Download PDF</a>
                                <a href="form.php?id=<?= (int)$meeting['id'] ?>" class="btn btn-sm btn-warning" title="Edit">Edit</a>
                                <form action="delete.php" method="POST" class="d-inline" data-confirm="Are you sure you want to delete this agenda? This action cannot be undone.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
