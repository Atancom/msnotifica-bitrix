<?php
$hasSettings = file_exists(__DIR__ . '/settings.php');
$webhook = '';
if ($hasSettings) {
    $conf = require __DIR__ . '/settings.php';
    $webhook = isset($conf['webhook']) ? htmlspecialchars($conf['webhook']) : '';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>MSNotifica ↔ Bitrix24 (CRest)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;max-width:780px;margin:32px auto;padding:0 16px}
    .grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .card{border:1px solid #ddd;border-radius:12px;padding:16px}
    a.button{display:inline-block;padding:10px 14px;background:#111;color:#fff;border-radius:10px;text-decoration:none}
    code{background:#f4f4f4;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
  <h1>MSNotifica ↔ Bitrix24</h1>
  <p>Panel de utilidades para pruebas.</p>

  <div class="grid">
    <div class="card">
      <h3>1) Configurar Webhook</h3>
      <p>Guarda tu URL de webhook de Bitrix24.</p>
      <a class="button" href="install.php">Abrir install.php</a>
    </div>
    <div class="card">
      <h3>2) Probar conexión</h3>
      <p>Ejecuta el método <code>profile</code>.</p>
      <a class="button" href="checkserver.php" target="_blank">Abrir checkserver.php</a>
    </div>
    <div class="card">
      <h3>3) Endpoint MSNotifica</h3>
      <p>Recibe datos (PDF, emisor, fecha, empresa) y crea un registro en Bitrix.</p>
      <a class="button" href="msnotifica_webhook.php" target="_blank">Ver ejemplo</a>
    </div>
  </div>

  <hr style="margin:24px 0">
  <h3>Estado</h3>
  <ul>
    <li><b>settings.php:</b> <?= $hasSettings ? 'Encontrado ✅' : 'No encontrado ❌' ?></li>
    <li><b>Webhook actual:</b> <?= $webhook ? $webhook : '—' ?></li>
  </ul>
</body>
</html>
