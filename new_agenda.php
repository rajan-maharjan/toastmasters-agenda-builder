<?php
require_once __DIR__ . '/includes/security.php';
// Keep old bookmarks working after correcting the navigation link.
header('Location: form.php', true, 302);
exit;
