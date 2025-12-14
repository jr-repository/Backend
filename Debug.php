<?php
require_once __DIR__ . '/autoload.php';
use App\Utils\Logger;

function writeLog($level, $message, $context = []) {
    Logger::write($level, $message, $context);
}