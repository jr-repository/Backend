<?php
namespace App\Core;

class Response
{
    public static function json($data, $status = 200, $success = true)
    {
        http_response_code($status);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-Timestamp, X-Api-Signature');
        header('Content-Type: application/json');

        echo json_encode([
            's' => $success,
            'd' => $data
        ]);
        exit;
    }

    public static function error($message, $status = 500)
    {
        http_response_code($status);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Content-Type: application/json');

        echo json_encode([
            's' => false,
            'message' => $message
        ]);
        exit;
    }
}