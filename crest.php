protected static function getWebhook()
{
    if (self::$webhook) return self::$webhook;

    // 1) Prioriza variable de entorno (persistente en Render)
    $env = getenv('BITRIX_WEBHOOK');
    if ($env) {
        return self::$webhook = rtrim($env, '/') . '/';
    }

    // 2) Fallback a settings.php (install.php)
    $settingsPath = __DIR__ . '/settings.php';
    if (!file_exists($settingsPath)) {
        throw new \RuntimeException('Falta settings.php. Abre install.php.');
    }
    $conf = require $settingsPath;
    if (!is_array($conf) || empty($conf['webhook'])) {
        throw new \RuntimeException('settings.php inválido. Vuelve a generar desde install.php');
    }
    return self::$webhook = rtrim($conf['webhook'], '/') . '/';
}
