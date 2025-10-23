<?php
class CRest
{
    protected static $webhook;
    protected static function getWebhook(){
        if (self::$webhook) return self::$webhook;
        $settingsPath = __DIR__.'/settings.php';
        if (!file_exists($settingsPath)) throw new RuntimeException('Falta settings.php. Abre install.php.');
        $conf = require $settingsPath;
        if (!is_array($conf) || empty($conf['webhook'])) throw new RuntimeException('settings.php inválido.');
        return self::$webhook = rtrim($conf['webhook'], '/') . '/';
    }
    public static function call(string $method, array $params = []){
        $url = self::getWebhook() . ltrim($method, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30
        ]);
        $raw = curl_exec($ch); $errno = curl_errno($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($errno) return ['error'=>'curl_error','error_description'=>'Fallo de red '.$errno,'http_code'=>$http];
        $json = json_decode($raw, true);
        return $json ?? ['error'=>'invalid_json','body'=>$raw,'http_code'=>$http];
    }
}
