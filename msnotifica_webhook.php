<?php
require __DIR__ . '/crest.php';

// Recibir datos del PDF enviado por MSNotifica
$pdfUrl  = trim($_POST['pdf_url'] ?? '');
$origen  = trim($_POST['origen'] ?? 'Desconocido');
$fecha   = trim($_POST['fecha'] ?? date('Y-m-d'));
$empresa = trim($_POST['empresa'] ?? 'Sin empresa');

if ($pdfUrl === '') {
    http_response_code(400);
    echo "Falta pdf_url";
    exit;
}

// Crear un registro en Bitrix (ejemplo: Deal)
$fields = [
    'TITLE'    => "Notificación $origen - $empresa",
    'COMMENTS' => "PDF recibido el $fecha desde MSNotifica: $pdfUrl",
    'UF_CRM_PDF_URL' => $pdfUrl
];

$response = CRest::call('crm.deal.add', ['fields' => $fields]);
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
