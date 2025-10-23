<?php
require __DIR__.'/crest.php';
header('Content-Type: application/json; charset=utf-8');
try{
  $r = CRest::call('profile', []);
  echo json_encode($r, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>'exception','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}
