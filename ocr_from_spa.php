<?php
require __DIR__ . '/crest.php';

/**
 * ocr_from_spa.php
 * - Acepta: key, entityTypeId, itemId, (opcional) pdf_url, (opcional) fileField
 * - Si no hay pdf_url: lee el campo de fichero, resuelve ID y crea/encontrar enlace público (external link)
 * - Llama al OCR y actualiza el SPA
 */

$EXPECTED_KEY = 'solfico-ocr-2025';
$got = $_GET['key'] ?? $_POST['key'] ?? '';
if (!hash_equals($EXPECTED_KEY, $got)) { http_response_code(401); exit('Unauthorized'); }

$in = $_POST ?: $_GET;

$pdfUrl       = trim($in['pdf_url'] ?? '');
$entityTypeId = (int)($in['entityTypeId'] ?? 0);
$itemId       = (int)($in['itemId'] ?? 0);
$fileField    = trim($in['fileField'] ?? 'UF_CRM_PDF_URL');

if (!$entityTypeId || !$itemId) { http_response_code(400); exit('missing ids'); }

// 1) Si NO nos pasan pdf_url, intentar resolver desde el campo de fichero:
//    - obtener fileId
//    - si no hay URL pública, crear external link y usarlo
if ($pdfUrl === '') {
    $item = CRest::call('crm.item.get', ['entityTypeId' => $entityTypeId, 'id' => $itemId]);
    $item = $item['result']['item'] ?? null;

    if ($item && isset($item[$fileField])) {
        $val = $item[$fileField];
        $fileId = null;

        // distintas formas que puede devolver Bitrix el fichero
        if (is_int($val) || (is_string($val) && ctype_digit($val))) {
            $fileId = (int)$val;
        } elseif (is_array($val)) {
            // ['id'=>123] | [{'id'=>123}] | ['VALUE'=>[123,...]] | ['downloadUrl'=>'...']
            if (isset($val['id'])) {
                $fileId = (int)$val['id'];
            } elseif (isset($val['VALUE']) && is_array($val['VALUE']) && !empty($val['VALUE'][0])) {
                $fileId = (int)$val['VALUE'][0];
            } elseif (isset($val[0]['id'])) {
                $fileId = (int)$val[0]['id'];
            } elseif (!empty($val['downloadUrl'])) {
                $pdfUrl = $val['downloadUrl'];
            }
        } elseif (is_string($val) && preg_match('~^https?://~i', $val)) {
            $pdfUrl = $val;
        }

        // si aún no tenemos URL, resolver por fileId
        if ($pdfUrl === '' && $fileId) {
            // 1) intenta DOWNLOAD_URL (suele llevar firma temporal y es público)
            $f = CRest::call('disk.file.get', ['id' => $fileId]);
            $download = $f['result']['DOWNLOAD_URL'] ?? '';

            // 2) si no hay DOWNLOAD_URL, crea external link público
            if ($download === '') {
                // crear (o reutilizar) external link
                $ext = CRest::call('disk.externalLink.add', [
                    'objectId' => $fileId,
                    'type'     => 'manual', // enlace público manual
                ]);
                $download = $ext['result']['LINK'] ?? '';
            }
            $pdfUrl = $download;
        }
    }
}

// si seguimos sin URL → error
if ($pdfUrl === '') {
    http_response_code(400);
    echo json_encode(['error'=>'missing_pdf_url','message'=>'No se pudo obtener URL pública del PDF'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) OCR (OCR.Space). Puedes poner OCR_SPACE_KEY en Variables de entorno; si no, usa 'helloworld' (demo)
$OCR_KEY = getenv('OCR_SPACE_KEY') ?: 'helloworld';
$ocrResp = ocr_space_by_url($pdfUrl, $OCR_KEY, 'spa');
$text    = $ocrResp['ParsedText'] ?? '';

// 3) Parseo simple
list($origen, $fecha, $expediente) = parse_campos($text);

// 4) Actualizar el item
$fieldsToUpdate = [
    'UF_CRM_ORIGEN'     => $origen ?: null,
    'UF_CRM_FECHA_DOC'  => $fecha ?: null,
    'UF_CRM_EXPEDIENTE' => $expediente ?: null,
    'COMMENTS'          => "OCR ejecutado.\nOrigen: " . ($origen ?: '-') . "\nFecha: " . ($fecha ?: '-') . "\nExpediente: " . ($expediente ?: '-') . "\nURL: $pdfUrl"
];
$fieldsToUpdate = array_filter($fieldsToUpdate, fn($v) => !is_null($v));

$update = CRest::call('crm.item.update', [
    'entityTypeId' => $entityTypeId,
    'id'           => $itemId,
    'fields'       => $fieldsToUpdate
]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'        => true,
    'pdf_url'   => $pdfUrl,
    'extracted' => ['origen'=>$origen, 'fecha'=>$fecha, 'expediente'=>$expediente],
    'update'    => $update
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// -------- helpers --------
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
    $text = $json['ParsedResults'][0]['ParsedText'] ?? '';
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


