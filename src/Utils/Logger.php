<?php
namespace App\Utils;

class Logger
{
    private static $logFile = __DIR__ . '/../../Debug.log';

    public static function write($level, $message, $context = [])
    {
        // $levels = ['ERROR', 'CRITICAL', 'INFO', 'DEBUG'];
        $level = strtoupper($level);

        if (!in_array($level, $levels)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message";

        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }

        file_put_contents(self::$logFile, $logEntry . "\n", FILE_APPEND);
    }
}