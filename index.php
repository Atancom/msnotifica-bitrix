<?php $has=file_exists(__DIR__.'/settings.php'); $w=$has?(require __DIR__.'/settings.php'):[]; $url=$w['webhook']??''; ?>
<!doctype html><html lang="es"><meta charset="utf-8"><title>MSNotifica ↔ Bitrix</title>
<style>body{font-family:system-ui;max-width:780px;margin:32px auto}a.btn{background:#111;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;margin-right:10px}</style>
<h1>MSNotifica ↔ Bitrix</h1>
<p><a class="btn" href="install.php">1) Configurar Webhook</a>
<a class="btn" href="checkserver.php" target="_blank">2) Probar conexión</a>
<a class="btn" href="msnotifica_webhook.php" target="_blank">3) Endpoint ejemplo</a></p>
<ul>
  <li><b>settings.php:</b> <?= $has?'OK ✅':'No encontrado ❌' ?></li>
  <li><b>Webhook actual:</b> <?= htmlspecialchars($url ?: '—') ?></li>
</ul>
</html>
