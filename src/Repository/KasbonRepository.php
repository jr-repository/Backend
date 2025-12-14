<?php
namespace App\Repository;

use App\Core\Database;

class KasbonRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll($keyword = '', $status = '')
    {
        $sql = "SELECT k.*, 
                (SELECT SUM(amount) FROM kasbon_details WHERE kasbon_id = k.id) as total_amount 
                FROM kasbon_transactions k WHERE 1=1";

        if (!empty($keyword)) {
            $sql .= " AND (k.transaction_number LIKE '%$keyword%' OR k.description LIKE '%$keyword%')";
        }
        if (!empty($status) && $status !== 'ALL') {
            $sql .= " AND k.status = '$status'";
        }

        $sql .= " ORDER BY k.trans_date DESC, k.id DESC LIMIT 100";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM kasbon_transactions WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findDetailsByKasbonId($id)
    {
        $stmt = $this->db->prepare("SELECT d.*, j.transaction_number as jo_number 
                                    FROM kasbon_details d 
                                    LEFT JOIN job_orders j ON d.job_order_id = j.id 
                                    WHERE d.kasbon_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function create($data, $creatorName)
    {
        $stmt = $this->db->prepare("INSERT INTO kasbon_transactions (transaction_number, trans_date, bank_id, bank_name, description, status, created_by) VALUES (?, ?, ?, ?, ?, 'WAITING_APPROVAL', ?)");
        $stmt->bind_param("ssisss", $data['trxNo'], $data['date'], $data['bankId'], $data['bankName'], $data['desc'], $creatorName);
        $stmt->execute();
        return $stmt->insert_id;
    }

    public function addDetail($kasbonId, $item)
    {
        $stmt = $this->db->prepare("INSERT INTO kasbon_details (kasbon_id, account_no, account_name, notes, amount, bill_amount, job_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssddi", $kasbonId, $item['accountNo'], $item['accountName'], $item['notes'], $item['amount'], $item['billAmount'], $item['jobOrderId']);
        return $stmt->execute();
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE kasbon_transactions SET trans_date = ?, bank_id = ?, bank_name = ?, description = ?, status = 'WAITING_APPROVAL' WHERE id = ?");
        $stmt->bind_param("sissi", $data['date'], $data['bankId'], $data['bankName'], $data['desc'], $id);
        return $stmt->execute();
    }

    public function deleteDetails($kasbonId)
    {
        $stmt = $this->db->prepare("DELETE FROM kasbon_details WHERE kasbon_id = ?");
        $stmt->bind_param("i", $kasbonId);
        return $stmt->execute();
    }

    public function updateStatus($id, $status, $accurateId = null)
    {
        if ($accurateId) {
            $stmt = $this->db->prepare("UPDATE kasbon_transactions SET status = ?, accurate_id = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $accurateId, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE kasbon_transactions SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
        }
        return $stmt->execute();
    }

    public function getDashboardSummary($startDate, $endDate)
    {
        $sqlSummary = "SELECT SUM(d.amount) as total_cost, SUM(d.bill_amount) as total_bill
                       FROM kasbon_details d
                       JOIN kasbon_transactions t ON d.kasbon_id = t.id
                       WHERE t.status != 'REJECTED' AND t.trans_date BETWEEN '$startDate' AND '$endDate'";
        return $this->db->query($sqlSummary)->fetch_assoc();
    }

    public function getJoPerformance($startDate, $endDate)
    {
        $sqlJO = "SELECT j.transaction_number as jo_number, j.customer_name, SUM(d.amount) as cost, SUM(d.bill_amount) as bill
                  FROM kasbon_details d
                  JOIN kasbon_transactions t ON d.kasbon_id = t.id
                  LEFT JOIN job_orders j ON d.job_order_id = j.id
                  WHERE t.status != 'REJECTED' AND t.trans_date BETWEEN '$startDate' AND '$endDate' AND d.job_order_id IS NOT NULL
                  GROUP BY d.job_order_id
                  ORDER BY (SUM(d.bill_amount) - SUM(d.amount)) DESC LIMIT 10";
        return $this->db->query($sqlJO)->fetch_all(MYSQLI_ASSOC);
    }

    public function getExpensesByJo($joId)
    {
        $sql = "SELECT kt.transaction_number, kt.trans_date, kt.description as header_desc, kd.account_name, kd.notes, kd.amount as cost, kd.bill_amount as bill
                FROM kasbon_details kd
                JOIN kasbon_transactions kt ON kt.id = kd.kasbon_id
                WHERE kd.job_order_id = ? AND kt.status != 'REJECTED' ORDER BY kt.trans_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $joId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findJobOrderByNumber($joNumber)
    {
        $stmt = $this->db->prepare("SELECT * FROM job_orders WHERE transaction_number = ?");
        $stmt->bind_param("s", $joNumber);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}