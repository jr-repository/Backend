<?php
namespace App\Utils;

class Cache
{
    private static $cacheDir = __DIR__ . '/../../temp/cache/';

    public static function get($key)
    {
        $file = self::$cacheDir . md5($key) . '.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['expiry']) && $data['expiry'] > time()) {
                return $data['payload'];
            }
            unlink($file);
        }
        return null;
    }

    public static function set($key, $payload, $ttl = 600)
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key) . '.json';
        $data = [
            'expiry' => time() + $ttl,
            'payload' => $payload
        ];
        file_put_contents($file, json_encode($data));
    }
}