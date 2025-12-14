<?php
require_once __DIR__ . '/../autoload.php';
use App\Utils\ActivityLogger as ActivityLoggerService;

if (!function_exists('logActivity')) {
    function logActivity($userId, $userName, $module, $action, $refNo, $amount = 0) {
        ActivityLoggerService::log($userId, $userName, $module, $action, $refNo, $amount);
    }
}