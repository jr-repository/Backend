<?php
namespace App\Security;

class Input
{
    public static function sanitize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value);
            }
        } else {
            $data = htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    public static function json()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? self::sanitize($input) : [];
    }
}