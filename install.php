<?php
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $webhook=trim($_POST['webhook']??'');
  if ($webhook===''){ $err='Introduce la URL del Webhook.'; }
  elseif(!preg_match('~^https?://.+/rest/\d+/[a-zA-Z0-9]+/?$~',$webhook)){ $err='URL de webhook no válida.'; }
  else{
    $content="<?php\nreturn [\n  'webhook' => '" . addslashes($webhook) . "'\n];\n";
    $ok = @file_put_contents(__DIR__.'/settings.php',$content);
    if($ok===false){ $err='No se pudo escribir settings.php (permisos).'; $ok=''; }
    else{ $ok='Guardado. Ahora prueba <a href=\"checkserver.php\" target=\"_blank\">checkserver.php</a>.'; }
  }
}
?><!doctype html><html lang="es"><meta charset="utf-8"><title>Configurar Webhook Bitrix24</title>
<style>body{font-family:system-ui;max-width:720px;margin:32px auto}input{width:100%;padding:12px;border:1px solid #bbb;border-radius:10px}button{padding:10px 16px;border:0;border-radius:10px;background:#111;color:#fff}</style>
<h1>Configurar Webhook de Bitrix24</h1>
<?php if($err):?><div style="background:#ffecec;border:1px solid #ff8d8d;padding:10px;border-radius:10px"><?=$err?></div><?php endif;?>
<?php if($ok):?><div style="background:#e8fff1;border:1px solid #8bd9a5;padding:10px;border-radius:10px"><?=$ok?></div><?php endif;?>
<form method="post"><p><b>URL del Webhook</b></p>
<input name="webhook" placeholder="https://crm.solfico.es/rest/XX/TOKEN/">
<p><button>Guardar</button></p></form>
<p><a href="index.php">Inicio</a></p>
</html>
