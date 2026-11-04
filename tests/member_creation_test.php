<?php
// Integration check against a migrated development database. Test records are removed afterwards.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';
function checkMember(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function memberRequest(array $input): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['csrf_token'] = 'member-test-token';
    $_POST = $input + ['csrf_token' => $_SESSION['csrf_token']];
    http_response_code(200);
    ob_start();
    try {
        require __DIR__ . '/../create_member.php';
        $body = ob_get_contents();
    } finally { ob_end_clean(); }
    return [http_response_code(), json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
$pdo = getDB();
$token = bin2hex(random_bytes(8));
$input = ['mobile_number' => '+97798' . random_int(10000000, 99999999), 'full_name' => 'Member regression ' . $token, 'intro' => 'Introduction ' . $token];
$createdId = null;
try {
    [$status, $data] = memberRequest($input);
    checkMember($status === 200 && isset($data['member']['member_id']), 'Creation without gender failed: ' . json_encode($data));
    $createdId = (string)$data['member']['member_id'];
    $stmt = $pdo->prepare('SELECT id, member_id, gender, intro FROM tin_members WHERE id = ?');
    $stmt->execute([$createdId]);
    $row = $stmt->fetch();
    checkMember($row && (string)$row['id'] === $createdId && $row['member_id'] === null && $row['gender'] === null, 'Response must use auto-generated ID and leave legacy ID/gender unset.');
    checkMember(isset(getRoleMembers()[$createdId]), 'New member is missing from agenda suggestions.');
    [$status] = memberRequest($input);
    checkMember($status === 422, 'Duplicate member should be rejected.');
    [$status] = memberRequest(array_replace($input, ['mobile_number' => 'invalid']));
    checkMember($status === 422, 'Invalid mobile should be rejected.');

    $meetingId = $pdo->query('SELECT id FROM tiab_meetings ORDER BY id LIMIT 1')->fetchColumn();
    checkMember($meetingId !== false, 'This integration check requires an existing development agenda.');
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO tiab_functional_roles (meeting_id, role_key, member_id) VALUES (?, ?, ?)')->execute([$meetingId, 'member_regression_test', $createdId]);
    $meeting = getMeetingFull((int)$meetingId);
    checkMember((string)$meeting['role_member_ids']['member_regression_test'] === $createdId, 'Agenda lost the generated member ID.');
    checkMember($meeting['roles']['member_regression_test'] === $input['full_name'], 'Agenda did not resolve the member name by primary ID.');
    checkMember($meeting['role_intros']['member_regression_test'] === $input['intro'], 'Agenda did not resolve the introduction by primary ID.');
    $pdo->rollBack();
    echo "PASS: creation without gender, generated ID, duplicate/invalid input, catalogue, agenda name and intro roundtrip.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($createdId !== null) {
        $pdo->prepare('DELETE FROM tin_members WHERE id = ? AND full_name = ?')->execute([$createdId, $input['full_name']]);
    }
}
