<?php
require __DIR__ . '/crest.php';

/**
 * ocr_from_spa.php
 * Lee un PDF desde un SPA de Bitrix y realiza OCR (texto automático)
 */

$EXPECTED_KEY = 'solfico-ocr-2025';
$got = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals($EXPECTED_KEY, $got)) {
    http_response_code(401);
    exit('Unauthorized');
}

// Recoge los parámetros de entrada
$in = $_POST;
if (!$in) { $in = $_GET; }

$pdfUrl       = trim($in['pdf_url'] ?? '');
$entityTypeId = isset($in['entityTypeId']) ? (int)$in['entityTypeId'] : 0;
$itemId       = isset($in['itemId']) ? (int)$in['itemId'] : 0;
$fileField    = trim($in['fileField'] ?? 'UF_CRM_PDF_URL');

// Si no se pasa pdf_url, intenta obtenerlo desde Bitrix
if ($pdfUrl === '' && $entityTypeId > 0 && $itemId > 0) {
    $resp = CRest::call('crm.item.get', ['entityTypeId' => $entityTypeId, 'id' => $itemId]);
    if (!empty($resp['result']['item'][$fileField])) {
        $pdfUrl = $resp['result']['item'][$fileField];
    }
}

if ($pdfUrl === '') {
    http_response_code(400);
    echo json_encode(['error'=>'missing_pdf_url','message'=>'No se encontró pdf_url'], JSON_UNESCAPED_UNICODE);
    exit;
}

// OCR con OCR.Space (modo gratuito)
$OCR_KEY = getenv('OCR_SPACE_KEY') ?: 'helloworld';
$ocrResult = ocr_space_by_url($pdfUrl, $OCR_KEY, 'spa');
$text = $ocrResult['ParsedText'] ?? '';

// Extrae algunos campos básicos
list($origen, $fecha, $expediente) = parse_campos($text);

$fields = [
    'UF_CRM_ORIGEN'     => $origen ?: '',
    'UF_CRM_FECHA_DOC'  => $fecha ?: '',
    'UF_CRM_EXPEDIENTE' => $expediente ?: '',
    'COMMENTS'          => "OCR ejecutado correctamente.\nOrigen: $origen\nFecha: $fecha\nExpediente: $expediente\nURL: $pdfUrl"
];

// Actualiza la tarjeta en Bitrix
if ($entityTypeId > 0 && $itemId > 0) {
    CRest::call('crm.item.update', [
        'entityTypeId' => $entityTypeId,
        'id' => $itemId,
        'fields' => $fields
    ]);
}

// Devuelve resultado
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'pdf_url' => $pdfUrl,
    'extracted' => ['origen' => $origen, 'fecha' => $fecha, 'expediente' => $expediente]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ------------------- FUNCIONES AUXILIARES -------------------
function ocr_space_by_url($url, $apikey, $lang='spa') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.ocr.space/parse/image',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'url' => $url,
            'language' => $lang,
            'apikey' => $apikey
        ],
        CURLOPT_TIMEOUT => 60
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['ParsedResults'][0] ?? [];
}

function parse_campos($text) {
    $text = strtoupper($text);
    $origen = '';
    if (preg_match('/AEAT|AGENCIA TRIBUTARIA/', $text)) $origen = 'AEAT';
    elseif (preg_match('/TGSS|SEGURIDAD SOCIAL/', $text)) $origen = 'TGSS';

    $fecha = '';
    if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $text, $m)) $fecha = $m[1];

    $expediente = '';
    if (preg_match('/EXPEDIENTE[:\s-]+([A-Z0-9\/\-]+)/', $text, $m)) $expediente = $m[1];

    return [$origen, $fecha, $expediente];
}
