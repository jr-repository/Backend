<?php
namespace App\Repository;

use App\Core\Database;

class MasterDataRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllTaxes()
    {
        $result = $this->db->query("SELECT * FROM master_taxes ORDER BY type ASC, rate ASC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function createTax($name, $rate, $type, $isDefault)
    {
        $stmt = $this->db->prepare("INSERT INTO master_taxes (name, rate, type, is_default) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdsi", $name, $rate, $type, $isDefault);
        return $stmt->execute();
    }

    public function updateTax($id, $name, $rate, $type, $isDefault)
    {
        $stmt = $this->db->prepare("UPDATE master_taxes SET name=?, rate=?, type=?, is_default=? WHERE id=?");
        $stmt->bind_param("sdsii", $name, $rate, $type, $isDefault, $id);
        return $stmt->execute();
    }

    public function deleteTax($id)
    {
        $stmt = $this->db->prepare("DELETE FROM master_taxes WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function resetDefaultTax($type)
    {
        $stmt = $this->db->prepare("UPDATE master_taxes SET is_default = 0 WHERE type = ?");
        $stmt->bind_param("s", $type);
        return $stmt->execute();
    }
}