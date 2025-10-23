<?php
require __DIR__ . '/crest.php';

/**
 * ocr_from_spa.php
 * - Entrada por POST/GET:
 *   - key (obligatorio)
 *   - entityTypeId, itemId (recomendado)
 *   - pdf_url (opcional)
 *   - fileField (opcional; por defecto UF_CRM_PDF_URL)
 * - Si no pasas pdf_url, intenta resolverlo leyendo el campo de la tarjeta (incluye soporte básico disco).
 * - OCR con OCR.Space (gratis para pruebas). Variable env: OCR_SPACE_KEY (si no, usa 'helloworld').
 */

$EXPECTED_KEY = 'solfico-ocr-2025';
$got = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals($EXPECTED_KEY, $got)) { http_response_code(401); exit('Unauthorized'); }

$in = $_POST; if (!$in) { $in = $_GET; }

$pdfUrl       = trim($in['pdf_url'] ?? '');
$entityTypeId = (int)($in['entityTypeId'] ?? 0);
$itemId       = (int)($in['itemId'] ?? 0);
$fileField    = trim($in['fileField'] ?? 'UF_CRM_PDF_URL');

// Intentar resolver URL desde el item si no nos pasaron pdf_url
if ($pdfUrl === '' && $entityTypeId > 0 && $itemId > 0) {
    $resp = CRest::call('crm.item.get', ['entityTypeId' => $entityTypeId, 'id' => $itemId]);
    if (!empty($resp['result']['item'])) {
        $item = $resp['result']['item'];
        if (!empty($item[$fileField])) {
            // Si es string, úsalo; si es array (disco), intenta sacar downloadUrl o id
            if (is_string($item[$fileField])) {
                $pdfUrl = $item[$fileField];
            } elseif (is_array($item[$fileField])) {
                // formados posibles: ['downloadUrl'=>...] o lista de archivos [['id'=>...], ...]
                if (!empty($item[$fileField]['downloadUrl'])) {
                    $pdfUrl = $item[$fileField]['downloadUrl'];
                } elseif (!empty($item[$fileField][0]['id'])) {
                    $fileId = $item[$fileField][0]['id'];
                    $f = CRest::call('disk.file.get', ['id' => $fileId]);
                    if (!empty($f['result']['DOWNLOAD_URL'])) {
                        $pdfUrl = $f['result']['DOWNLOAD_URL'];
                    }
                }
            }
        }
    }
}

if ($pdfUrl === '') {
    http_response_code(400);
    echo json_encode(['error'=>'missing_pdf_url','message'=>'No se encontró pdf_url ni se pudo resolver desde el item'], JSON_UNESCAPED_UNICODE);
    exit;
}

// OCR
$OCR_KEY = getenv('OCR_SPACE_KEY') ?: 'helloworld';
$ocrResp = ocr_space_by_url($pdfUrl, $OCR_KEY, 'spa');
$text    = $ocrResp['ParsedText'] ?? '';

// Parseo simple
list($origen, $fecha, $expediente) = parse_campos($text);

// Armar actualización
$fieldsToUpdate = [
    'UF_CRM_ORIGEN'     => $origen ?: null,
    'UF_CRM_FECHA_DOC'  => $fecha ?: null,
    'UF_CRM_EXPEDIENTE' => $expediente ?: null,
];
$fieldsToUpdate = array_filter($fieldsToUpdate, fn($v) => !is_null($v));
$comment = "OCR ejecutado.\nOrigen: " . ($origen ?: '-') . "\nFecha: " . ($fecha ?: '-') . "\nExpediente: " . ($expediente ?: '-') . "\nURL: $pdfUrl";
$fieldsToUpdate['COMMENTS'] = $comment;

$update = null;
if ($entityTypeId > 0 && $itemId > 0) {
    $update = CRest::call('crm.item.update', [
        'entityTypeId' => $entityTypeId,
        'id'           => $itemId,
        'fields'       => $fieldsToUpdate
    ]);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'pdf_url'  => $pdfUrl,
    'extracted'=> ['origen'=>$origen, 'fecha'=>$fecha, 'expediente'=>$expediente],
    'update'   => $update
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ---------- helpers ----------
function ocr_space_by_url($url, $apikey, $lang='spa') {
    $endpoint = 'https://api.ocr.space/parse/image';
    $post = [
        'url' => $url,
        'language' => $lang,
        'isOverlayRequired' => 'false',
        'OCREngine' => 2,
        'scale' => 'true',
        'filetype' => 'PDF',
        'detectOrientation' => 'true',
        'isTable' => 'true',
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => ['apikey: '.$apikey],
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err) return ['error'=>'ocr_curl','message'=>$err];
    $json = json_decode($raw, true);
    if (!$json || !empty($json['IsErroredOnProcessing'])) return ['error'=>'ocr_error','raw'=>$raw];
    $text = !empty($json['ParsedResults'][0]['ParsedText']) ? $json['ParsedResults'][0]['ParsedText'] : '';
    return ['ParsedText'=>$text,'raw'=>$json];
}

function parse_campos($txt) {
    $t = preg_replace("/[ \t]+/", " ", str_replace(["\r","\n"], " ", $txt));
    $origen = '';
    if (preg_match('~\b(AEAT|TGSS|DGT|SEGURIDAD SOCIAL|AGENCIA TRIBUTARIA)\b~i', $t, $m)) {
        $origen = strtoupper($m[1]);
        if ($origen === 'AGENCIA TRIBUTARIA') $origen = 'AEAT';
        if ($origen === 'SEGURIDAD SOCIAL')   $origen = 'TGSS';
    }
    $fecha = '';
    if (preg_match('~\b(\d{2}/\d{2}/\d{4})\b~', $t, $m)) {
        $d = DateTime::createFromFormat('d/m/Y', $m[1]); if ($d) $fecha = $d->format('Y-m-d');
    } elseif (preg_match('~\b(\d{4}-\d{2}-\d{2})\b~', $t, $m)) {
        $fecha = $m[1];
    }
    $exped = '';
    if (preg_match('~(EXPEDIENTE[:\s#-]+)([A-Z0-9\/\-]{5,})~i', $t, $m)) {
        $exped = strtoupper($m[2]);
    } elseif (preg_match('~\b([A-Z]{2,4}\/\d{3,}|\d{3,}\/[A-Z]{2,4})\b~', $t, $m)) {
        $exped = strtoupper($m[1]);
    }
    return [$origen, $fecha, $exped];
}

