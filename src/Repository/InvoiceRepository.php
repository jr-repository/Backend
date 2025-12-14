<?php
namespace App\Repository;

use App\Core\Database;

class InvoiceRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAll($keyword = '')
    {
        $sql = "SELECT *, 
                CASE 
                    WHEN status IN ('SUBMITTED', 'WAITING_APPROVAL', 'DRAFT') AND due_date IS NOT NULL AND due_date != '0000-00-00'
                    THEN DATEDIFF(CURDATE(), due_date)
                    ELSE 0
                END AS aging_days
                FROM invoices WHERE 1=1";
        
        $params = [];
        $types = "";

        if (!empty($keyword)) {
            $sql .= " AND (transaction_number LIKE ? OR customer_name LIKE ?)";
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
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function findDetailsByInvoiceId($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM invoice_details WHERE invoice_id = ?");
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
        $stmt = $this->db->prepare("INSERT INTO invoices (transaction_number, trans_date, customer_no, customer_name, description, subtotal, global_disc_percent, global_disc_amount, tax_ppn_amount, tax_pph_amount, total_amount, down_payment, due_date, net_balance, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'WAITING_APPROVAL')");
        $stmt->bind_param("sssssdddddddsd", 
            $data['trxNo'], $data['transDate'], $data['custNo'], $data['custName'], 
            $data['desc'], $data['subtotal'], $data['globalDiscPercent'], 
            $data['globalDiscAmount'], $data['totalPPN'], $data['totalPPh'], 
            $data['grandTotal'], $data['downPayment'], $data['dueDate'], $data['netBalance']
        );
        $stmt->execute();
        return $stmt->insert_id;
    }

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE invoices SET trans_date=?, customer_no=?, customer_name=?, description=?, subtotal=?, global_disc_percent=?, global_disc_amount=?, tax_ppn_amount=?, tax_pph_amount=?, total_amount=?, down_payment=?, due_date=?, net_balance=?, status='WAITING_APPROVAL' WHERE id=?");
        $stmt->bind_param("ssssdddddddsdi", 
            $data['transDate'], $data['custNo'], $data['custName'], 
            $data['desc'], $data['subtotal'], $data['globalDiscPercent'], 
            $data['globalDiscAmount'], $data['totalPPN'], $data['totalPPh'], 
            $data['grandTotal'], $data['downPayment'], $data['dueDate'], 
            $data['netBalance'], $id
        );
        return $stmt->execute();
    }

    public function deleteDetails($invoiceId)
    {
        $stmt = $this->db->prepare("DELETE FROM invoice_details WHERE invoice_id = ?");
        $stmt->bind_param("i", $invoiceId);
        return $stmt->execute();
    }

    public function addDetail($invoiceId, $item)
    {
        $stmt = $this->db->prepare("INSERT INTO invoice_details (invoice_id, item_no, item_name, quantity, unit_price, item_disc_percent, item_disc_amount, ppn_rate, pph_rate, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddsdddd", 
            $invoiceId, $item['item_no'], $item['item_name'], $item['quantity'], 
            $item['unit_price'], $item['item_disc_percent'], $item['item_disc_amount'], 
            $item['ppn_rate'], $item['pph_rate'], $item['line_total']
        );
        return $stmt->execute();
    }

    public function updateStatus($id, $status, $accurateId = null, $accurateNo = null)
    {
        if ($accurateId && $accurateNo) {
            $stmt = $this->db->prepare("UPDATE invoices SET status=?, accurate_id=?, transaction_number=? WHERE id=?");
            $stmt->bind_param("sssi", $status, $accurateId, $accurateNo, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE invoices SET status=? WHERE id=?");
            $stmt->bind_param("si", $status, $id);
        }
        return $stmt->execute();
    }

    public function updatePaymentStatus($id, $status, $downPayment, $netBalance)
    {
        $stmt = $this->db->prepare("UPDATE invoices SET status=?, down_payment=?, net_balance=? WHERE id=?");
        $stmt->bind_param("sddi", $status, $downPayment, $netBalance, $id);
        return $stmt->execute();
    }

    public function addPaymentFile($invoiceId, $fileName, $filePath, $uploadedBy)
    {
        $stmt = $this->db->prepare("INSERT INTO invoice_payment_files (invoice_id, file_name, file_path, uploaded_by_user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $invoiceId, $fileName, $filePath, $uploadedBy);
        return $stmt->execute();
    }

    public function getPaymentFiles($invoiceId)
    {
        $stmt = $this->db->prepare("SELECT file_name, file_path FROM invoice_payment_files WHERE invoice_id = ?");
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}