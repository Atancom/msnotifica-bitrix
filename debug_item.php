<?php
require __DIR__ . '/crest.php';

// Seguridad simple
$EXPECTED_KEY = 'solfico-ocr-2025';
$got = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals($EXPECTED_KEY, $got)) { http_response_code(401); exit('Unauthorized'); }

$entityTypeId = (int)($_GET['entityTypeId'] ?? 0);
$itemId       = (int)($_GET['itemId'] ?? 0);
if (!$entityTypeId || !$itemId) { http_response_code(400); exit('missing ids'); }

$r = CRest::call('crm.item.get', ['entityTypeId'=>$entityTypeId, 'id'=>$itemId]);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
