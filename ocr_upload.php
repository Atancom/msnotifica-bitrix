<?php
require __DIR__ . '/crest.php';

$EXPECTED_KEY = 'solfico-ocr-2025';
$key = $_GET['key'] ?? '';
if (!hash_equals($EXPECTED_KEY, $key)) { http_response_code(401); exit('Unauthorized'); }

$entityTypeId = (int)($_GET['entityTypeId'] ?? 0);
$itemId       = (int)($_GET['itemId'] ?? 0);
if (!$entityTypeId || !$itemId) { http_response_code(400); exit('missing ids'); }

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  http_response_code(400);
  exit('missing file');
}

// ===== Enviar el PDF a OCR.Space por subida de archivo =====
$OCR_KEY = getenv('OCR_SPACE_KEY') ?: 'helloworld';
$endpoint = 'https://api.ocr.space/parse/image';
$post = [
  'language' => 'spa',
  'isOverlayRequired' => 'false',
  'OCREngine' => 2,
  'scale' => 'true',
  'filetype' => 'PDF',
  'detectOrientation' => 'true',
  'isTable' => 'true',
  'file' => new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'] ?? 'application/pdf', $_FILES['file']['name'] ?? 'document.pdf')
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $post,
  CURLOPT_HTTPHEADER => ['apikey: '.$OCR_KEY],
  CURLOPT_TIMEOUT => 90,
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($raw === false || $err) { http_response_code(502); exit('ocr_curl_error: '.$err); }
$j = json_decode($raw, true);
if (!$j || !empty($j['IsErroredOnProcessing'])) { http_response_code(502); exit('ocr_failed'); }
$text = $j['ParsedResults'][0]['ParsedText'] ?? '';

// ===== Parseo sencillo =====
function parse_campos($txt) {
  $t = preg_replace("/[ \t]+/", " ", str_replace(["\r","\n"], " ", $txt));
  $origen = ''; if (preg_match('~\b(AEAT|TGSS|DGT|SEGURIDAD SOCIAL|AGENCIA TRIBUTARIA)\b~i', $t, $m)) { $origen = strtoupper($m[1]); if ($origen==='AGENCIA TRIBUTARIA') $origen='AEAT'; if ($origen==='SEGURIDAD SOCIAL') $origen='TGSS'; }
  $fecha  = ''; if (preg_match('~\b(\d{2}/\d{2}/\d{4})\b~', $t, $m)) { $d = DateTime::createFromFormat('d/m/Y',$m[1]); if ($d) $fecha = $d->format('Y-m-d'); } elseif (preg_match('~\b(\d{4}-\d{2}-\d{2})\b~', $t, $m)) { $fecha = $m[1]; }
  $exp    = ''; if (preg_match('~(EXPEDIENTE[:\s#-]+)([A-Z0-9\/\-]{5,})~i', $t, $m)) { $exp = strtoupper($m[2]); } elseif (preg_match('~\b([A-Z]{2,4}\/\d{3,}|\d{3,}\/[A-Z]{2,4})\b~', $t, $m)) { $exp = strtoupper($m[1]); }
  return [$origen,$fecha,$exp];
}
list($origen,$fecha,$expediente) = parse_campos($text);

// ===== Actualizar el item del SPA =====
$fields = [
  'COMMENTS' => "OCR ejecutado.\nOrigen: ".($origen?:'-')."\nFecha: ".($fecha?:'-')."\nExpediente: ".($expediente?:'-')
];
// añade aquí tus UF si existen (quita los comentarios):
// $fields['UF_CRM_ORIGEN']     = $origen ?: null;
// $fields['UF_CRM_FECHA_DOC']  = $fecha ?: null;
// $fields['UF_CRM_EXPEDIENTE'] = $expediente ?: null;
$fields = array_filter($fields, fn($v)=>!is_null($v));

$update = CRest::call('crm.item.update', [
  'entityTypeId' => $entityTypeId,
  'id'           => $itemId,
  'fields'       => $fields
]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'update'=>$update], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
