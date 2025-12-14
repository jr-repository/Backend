<?php
namespace App\Utils;

use App\Core\Database;

class ActivityLogger
{
    public static function log($userId, $userName, $module, $action, $refNo, $amount = 0)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO system_activity_logs (user_id, user_name, module, action, reference_number, transaction_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $valAmount = floatval($amount);
            $stmt->bind_param("issssd", $userId, $userName, $module, $action, $refNo, $valAmount);
            $stmt->execute();
        } catch (\Exception $e) {
            Logger::write('ERROR', 'Failed to log activity', ['error' => $e->getMessage()]);
        }
    }
}