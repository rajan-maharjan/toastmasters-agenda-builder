<?php
require_once __DIR__ . '/includes/security.php';
requirePostAndCsrf();
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=UTF-8');
$pdo = getDB();
try {
    $values = [];
    foreach (['member_id'=>10, 'full_name'=>500, 'club_id'=>10, 'gender'=>1, 'intro'=>1500] as $field=>$limit) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value)>$limit || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid ' . $field . '.');
        $values[$field] = trim($value);
    }
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $values['member_id']) || (float)$values['member_id']>2147483647) throw new InvalidArgumentException('Enter only numeric value from member ID (PN-XXXXXXX).');
    if ($values['full_name']==='') throw new InvalidArgumentException('Enter the full name.');
    if (!in_array($values['gender'], ['M','F'], true)) throw new InvalidArgumentException('Select gender.');
    $stmt=$pdo->prepare("SELECT club_id FROM tin_clubs WHERE club_id=?");
    $stmt->execute([$values['club_id']]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Select a club.');
    // Serialize creation because the imported member table has no unique member_id key.
    if (!$pdo->query("SELECT GET_LOCK('agenda_member_creation', 5)")->fetchColumn()) throw new RuntimeException('Please retry.');
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT id FROM tin_members WHERE member_id=? OR TRIM(full_name)=? LIMIT 1');
        $stmt->execute([$values['member_id'],$values['full_name']]);
        if ($stmt->fetchColumn()) throw new InvalidArgumentException('A member with this ID or name already exists. Select their club to find them in the suggestions.');
        $stmt=$pdo->prepare("INSERT INTO tin_members (member_id,full_name,first_name,middle_name,last_name,email,current_do_position,mobile_number,gender,skill_ids,intro,profile_link,profile_picture,updated_by,updated_on) VALUES (?,?,'','','','','','',?,'',?,'','',0,CURDATE())");
        $stmt->execute([$values['member_id'],$values['full_name'],$values['gender'],$values['intro']]);
        $stmt=$pdo->prepare("INSERT INTO tin_members_club_role (member_id,club_id,role_code,fy_id) VALUES (?,?,'MEM',5)");
        $stmt->execute([$values['member_id'],$values['club_id']]);
        $member=getRoleMembers()[$values['member_id']];
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->query("SELECT RELEASE_LOCK('agenda_member_creation')");
    }
    echo json_encode(['member'=>$member], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo json_encode(['error'=>'Could not add the member. Please try again.']);
}
