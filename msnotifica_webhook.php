<?php
require __DIR__ . '/crest.php';

/**
 * Webhook receptor de MSNotifica ↔ Bitrix24
 * Acepta datos por POST (MSNotifica) o GET (para probar manualmente)
 * Requiere MSNOTIFICA_KEY si está configurada en Render
 */

// 1️⃣ Protección opcional (usa la clave en Environment)
$EXPECTED_KEY = getenv('MSNOTIFICA_KEY') ?: '';
if ($EXPECTED_KEY !== '') {
    $got = $_GET['key'] ?? $_POST['key'] ?? '';
    if (!hash_equals($EXPECTED_KEY, $got)) {
        http_response_code(401);
        exit('Unauthorized');
    }
}

// 2️⃣ Recoger datos (funciona con POST o GET)
$input = $_POST;
if (empty($input)) { $input = $_GET; }

$pdfUrl   = trim($input['pdf_url'] ?? '');
$origen   = trim($input['origen'] ?? 'Desconocido');
$fecha    = trim($input['fecha'] ?? date('Y-m-d'));
$empresa  = trim($input['empresa'] ?? 'Sin empresa');
$exped    = trim($input['expediente'] ?? '');

if ($pdfUrl === '') {
    http_response_code(400);
    exit('Falta pdf_url');
}

// 3️⃣ Preparar los datos para Bitrix
$fields = [
    'TITLE'    => "Notificación $origen - $empresa",
    'COMMENTS' => "PDF recibido el $fecha\nExpediente: $exped\nOrigen: $origen\nURL: $pdfUrl",
    'UF_CRM_PDF_URL' => $pdfUrl
];

// 4️⃣ Crear un registro en Bitrix (Deal)
$payload = ['fields' => $fields];
$response = CRest::call('crm.deal.add', $payload);

// 5️⃣ Mostrar resultado
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
