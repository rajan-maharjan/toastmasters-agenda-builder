<?php
require_once __DIR__ . '/includes/security.php';
requirePostAndCsrf();
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    $values = [];
    foreach (['mobile_number'=>20, 'full_name'=>500, 'intro'=>1500] as $field=>$limit) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value)>$limit || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid ' . $field . '.');
        $values[$field] = trim($value);
    }
    if (!preg_match('/^(?:(?:\+977|977)?9[5-8]\d{8}|(?:\+91|91)?[6-9]\d{9}|(?:\+880|880|0)?1[3-9]\d{8})$/D', $values['mobile_number'])) throw new InvalidArgumentException('Enter proper mobile number (e.g: +9779851122778).');
    if ($values['full_name']==='') throw new InvalidArgumentException('Enter the full name.');
    $pdo = getDB();
    // Serialize creation because the imported member table has no unique mobile number key.
    if (!$pdo->query("SELECT GET_LOCK('agenda_member_creation', 5)")->fetchColumn()) throw new RuntimeException('Please retry.');
    try {
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('SELECT id FROM tin_members WHERE mobile_number=? OR TRIM(full_name)=? LIMIT 1');
        $stmt->execute([$values['mobile_number'],$values['full_name']]);
        if ($stmt->fetchColumn()) throw new InvalidArgumentException('A member with this mobile number or name already exists. Select their club to find them in the suggestions.');
        $stmt=$pdo->prepare("INSERT INTO tin_members (mobile_number,full_name,intro,email,current_do_position,skill_ids,profile_link,updated_by,updated_on) VALUES (?,?,?,'','','','',0,CURDATE())");
        $stmt->execute([$values['mobile_number'],$values['full_name'],$values['intro']]);
        $memberId = $pdo->lastInsertId();
        $member = getRoleMembers()[$memberId];
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
