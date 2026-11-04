<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/agenda.php';
foreach ([
    [['presiding_officer' => 'Opening member'], 'Opening member'],
    [['presiding_officer' => 'Opening member', 'closing_presiding_officer' => 'Closing member'], 'Closing member'],
] as [$roles, $expectedClosing]) {
    $blocks = array_column(buildAgenda(['roles' => $roles])['blocks'], null, 'key');
    if ($blocks['presiding_officer_intro']['person'] !== 'Opening member'
        || $blocks['adjourn']['person'] !== $expectedClosing
        || $blocks['adjourn']['role'] !== 'closing_presiding_officer') {
        throw new RuntimeException('Opening/closing PO rendering failed.');
    }
}
echo "PASS: legacy PO fallback and independent closing PO render correctly.\n";
