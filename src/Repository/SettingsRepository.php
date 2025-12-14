<?php
namespace App\Repository;

use App\Core\Database;

class SettingsRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll()
    {
        $result = $this->db->query("SELECT setting_key, setting_value, label, description FROM system_settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row;
        }
        return $settings;
    }

    public function updateBatch(array $settings)
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            foreach ($settings as $key => $value) {
                $valStr = is_string($value) ? $value : strval($value);
                $stmt->bind_param("ss", $valStr, $key);
                $stmt->execute();
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}