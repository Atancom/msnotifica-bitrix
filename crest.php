<?php
class CRest {
    protected static $webhook = null;

    protected static function getWebhook() {
        if (self::$webhook) { return self::$webhook; }

        // 1) Variable de entorno (persistente en Render)
        $env = getenv('BITRIX_WEBHOOK');
        if ($env && trim($env) !== '') {
            self::$webhook = rtrim($env, '/') . '/';
            return self::$webhook;
        }

        // 2) Fallback: settings.php creado por install.php
        $settingsPath = __DIR__ . '/settings.php';
        if (file_exists($settingsPath)) {
            $conf = require $settingsPath;
            if (is_array($conf) && !empty($conf['webhook'])) {
                self::$webhook = rtrim($conf['webhook'], '/') . '/';
                return self::$webhook;
            }
        }

        throw new RuntimeException('Falta settings.php o variable BITRIX_WEBHOOK.');
    }

    public static function call($method, $params = []) {
        $url = self::getWebhook() . ltrim($method, '/');
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw  = curl_exec($ch);
        $errno= curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['error'=>'curl_error','error_description'=>'Fallo de red: '.$errno,'http_code'=>$http];
        }
        if ($raw === false) {
            return ['error'=>'empty_response','error_description'=>'Respuesta vacía','http_code'=>$http];
        }

        $json = json_decode($raw, true);
        if ($json === null) {
            return ['error'=>'invalid_json','error_description'=>'JSON no válido: '.$raw,'http_code'=>$http];
        }
        if (!isset($json['http_code'])) { $json['http_code'] = $http; }
        return $json;
    }
}

