<?php
/**
 * CRest mínimo para Webhook Entrante Bitrix24 (versión persistente)
 *  - Prioriza variable de entorno BITRIX_WEBHOOK (ideal para Render)
 *  - Fallback: lee settings.php si existe
 */

class CRest
{
    protected static $webhook;

    /**
     * Devuelve la URL base del webhook configurado
     */
    protected static function getWebhook()
    {
        // Si ya está en memoria, la devolvemos
        if (self::$webhook) return self::$webhook;

        // 1️⃣ INTENTA LEER DE VARIABLE DE ENTORNO (persistente en Render)
        $env = getenv('BITRIX_WEBHOOK');
        if ($env && trim($env) !== '') {
            self::$webhook = rtrim($env, '/') . '/';
            return self::$webhook;
        }

        // 2️⃣ SI NO EXISTE, BUSCA EN settings.php (creado por install.php)
        $settingsPath = __DIR__ . '/settings.php';
        if (!file_exists($settingsPath)) {
            throw new \RuntimeException('Falta settings.php. Abre install.php.');
        }

        $conf = require $settingsPath;
        if (!is_array($conf) || empty($conf['webhook'])) {
            throw new \RuntimeException('settings.php inválido. Vuelve a generar desde install.php.');
        }

        self::$webhook = rtrim($conf['webhook'], '/') . '/';
        return self::$webhook;
    }

    /**
     * Llama a un método REST de Bitrix24
     * @param string $method Nombre del método (ej. crm.deal.add)
     * @param array $params  Parámetros de la llamada
     * @return array Respuesta JSON decodificada
     */
    public static function call(string $method, array $params = [])
    {
        $url = self::getWebhook() . l
