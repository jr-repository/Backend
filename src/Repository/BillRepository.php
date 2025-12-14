<?php
namespace App\Repository;

use App\Core\Database;

class BillRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll($keyword = '')
    {
        $sql = "SELECT * FROM bills WHERE 1=1";
        $params = [];
        $types = "";

        if (!empty($keyword)) {
            $sql .= " AND (transaction_number LIKE ? OR vendor_name LIKE ?)";
            $searchTerm = "%$keyword%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types = "ss";
        }

        $sql .= " ORDER BY trans_date DESC, id DESC LIMIT 50";

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM bills WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findDetailsByBillId($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM bill_details WHERE bill_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTransactionNumberById($id)
    {
        $row = $this->findById($id);
        return $row ? $row['transaction_number'] : null;
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("INSERT INTO bills (transaction_number, trans_date, vendor_no, vendor_name, description, subtotal, global_disc_percent, global_disc_amount, tax_ppn_amount, tax_pph_amount, total_amount, down_payment, net_balance, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'WAITING_APPROVAL')");
        $stmt->bind_param("sssssdddddddd", 
            $data['trxNo'], $data['transDate'], $data['vendNo'], $data['vendName'], 
            $data['desc'], $data['subtotal'], $data['globalDiscPercent'], 
            $data['globalDiscAmount'], $data['totalPPN'], $data['totalPPh'], 
            $data['grandTotal'], $data['downPayment'], $data['netBalance']
        );
        $stmt->execute();
        return $stmt->insert_id;
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE bills SET trans_date=?, vendor_no=?, vendor_name=?, description=?, subtotal=?, global_disc_percent=?, global_disc_amount=?, tax_ppn_amount=?, tax_pph_amount=?, total_amount=?, down_payment=?, net_balance=?, status='WAITING_APPROVAL' WHERE id=?");
        $stmt->bind_param("ssssddddddddi", 
            $data['transDate'], $data['vendNo'], $data['vendName'], 
            $data['desc'], $data['subtotal'], $data['globalDiscPercent'], 
            $data['globalDiscAmount'], $data['totalPPN'], $data['totalPPh'], 
            $data['grandTotal'], $data['downPayment'], $data['netBalance'], $id
        );
        return $stmt->execute();
    }

    public function deleteDetails($billId)
    {
        $stmt = $this->db->prepare("DELETE FROM bill_details WHERE bill_id = ?");
        $stmt->bind_param("i", $billId);
        return $stmt->execute();
    }

    public function addDetail($billId, $item)
    {
        $stmt = $this->db->prepare("INSERT INTO bill_details (bill_id, item_no, item_name, quantity, unit_price, item_disc_percent, item_disc_amount, ppn_rate, pph_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddsdddd", 
            $billId, $item['item_no'], $item['item_name'], $item['quantity'], 
            $item['unit_price'], $item['item_disc_percent'], $item['item_disc_amount'], 
            $item['ppn_rate'], $item['pph_rate'], $item['line_total']
        );
        return $stmt->execute();
    }

    public function updateStatus($id, $status, $accurateId = null)
    {
        if ($accurateId) {
            $stmt = $this->db->prepare("UPDATE bills SET status=?, accurate_id=? WHERE id=?");
            $stmt->bind_param("ssi", $status, $accurateId, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE bills SET status=? WHERE id=?");
            $stmt->bind_param("si", $status, $id);
        }
        return $stmt->execute();
    }
}