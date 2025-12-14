<?php
namespace App\Repository;

use App\Core\Database;

class JobOrderRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll($keyword = '')
    {
        $sql = "SELECT * FROM job_orders WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($keyword)) {
            $sql .= " AND (transaction_number LIKE ? OR customer_name LIKE ? OR pic LIKE ?)";
            $searchTerm = "%$keyword%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
            $types = "sss";
        }

        $sql .= " ORDER BY trans_date DESC, id DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM job_orders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findItemsByJobOrderId($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM job_order_items WHERE job_order_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("INSERT INTO job_orders (transaction_number, trans_date, customer_no, customer_name, pic, description, status, accurate_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", 
            $data['trxNo'], $data['transDate'], $data['custNo'], 
            $data['custName'], $data['pic'], $data['desc'], 
            $data['status'], $data['accurateId']
        );
        $stmt->execute();
        return $stmt->insert_id;
    }

    public function addItem($jobOrderId, $item)
    {
        $stmt = $this->db->prepare("INSERT INTO job_order_items (job_order_id, item_no, item_name, quantity) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $jobOrderId, $item['itemNo'], $item['itemName'], $item['quantity']);
        return $stmt->execute();
    }
}