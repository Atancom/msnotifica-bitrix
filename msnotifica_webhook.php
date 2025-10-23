<?php
require __DIR__.'/crest.php';
$EXPECTED_KEY = getenv('MSNOTIFICA_KEY') ?: '';
if ($EXPECTED_KEY!==''){
  $got = $_GET['key'] ?? $_POST['key'] ?? '';
  if (!hash_equals($EXPECTED_KEY, $got)){ http_response_code(401); exit('Unauthorized'); }
}
$pdfUrl  = trim($_POST['pdf_url'] ?? '');
$origen  = trim($_POST['origen']  ?? 'Organismo');
$fecha   = trim($_POST['fecha']   ?? date('Y-m-d'));
$empresa = trim($_POST['empresa'] ?? 'Empresa');
$exped   = trim($_POST['expediente'] ?? '');
if ($pdfUrl===''){ http_response_code(400); exit('Falta pdf_url'); }
$fields = [
  'TITLE'=>"Notificación $origen - $empresa",
  'COMMENTS'=>"PDF: $pdfUrl\nFecha: $fecha\nExpediente: $exped",
  'UF_CRM_ORIGEN'=>$origen,
  'UF_CRM_FECHA_DOC'=>$fecha,
  'UF_CRM_EXPEDIENTE'=>$exped,
  'UF_CRM_PDF_URL'=>$pdfUrl,
];
$payload = ['fields'=>$fields, 'params'=>['REGISTER_SONET_EVENT'=>'N']];
header('Content-Type: application/json; charset=utf-8');
try{ echo json_encode(CRest::call('crm.deal.add',$payload), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); }
catch(Throwable $e){ http_response_code(500); echo json_encode(['error'=>'exception','message'=>$e->getMessage()]); }
