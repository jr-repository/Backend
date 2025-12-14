<?php
namespace App\Repository;

use App\Core\Database;

class RekonRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAllMandiri($keyword = '', $status = '', $fromDate = '', $toDate = '')
    {
        $sql = "SELECT r.*, 
                uc.name as creator_name,
                COALESCE(r.updated_by, '-') as updater_name_display,
                (SELECT GROUP_CONCAT(file_path SEPARATOR ',') FROM bank_reconciliation_files WHERE rekon_id = r.id) as files 
                FROM bank_reconciliations r 
                LEFT JOIN users uc ON r.created_by = uc.id
                WHERE 1=1";

        if (!empty($keyword)) {
            $sql .= " AND (r.transaction_code LIKE '%$keyword%' OR r.description1 LIKE '%$keyword%' OR r.description2 LIKE '%$keyword%' OR r.reference_no LIKE '%$keyword%')";
        }
        if (!empty($status) && $status !== 'ALL') {
            $sql .= " AND r.status = '$status'";
        }
        if (!empty($fromDate)) {
            $sql .= " AND DATE(r.date) >= '$fromDate'";
        }
        if (!empty($toDate)) {
            $sql .= " AND DATE(r.date) <= '$toDate'";
        }

        $sql .= " ORDER BY r.date DESC, r.id DESC";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function findAllCimb($keyword = '', $status = '', $fromDate = '', $toDate = '')
    {
        $sql = "SELECT r.*, 
                uc.name as creator_name,
                uu.name as updater_name,
                (SELECT GROUP_CONCAT(file_path SEPARATOR ',') FROM bank_reconciliation_files_cimb WHERE rekon_id = r.id) as files 
                FROM bank_reconciliations_cimb r 
                LEFT JOIN users uc ON r.created_by = uc.id
                LEFT JOIN users uu ON r.updated_by = uu.id
                WHERE 1=1";

        if (!empty($keyword)) {
            $sql .= " AND (r.cheque_no LIKE '%$keyword%' OR r.description LIKE '%$keyword%' OR r.transaction_code LIKE '%$keyword%' OR r.reference_no LIKE '%$keyword%' OR r.bank_reference LIKE '%$keyword%')";
        }
        if (!empty($status) && $status !== 'ALL') {
            $sql .= " AND r.status = '$status'";
        }
        if (!empty($fromDate)) {
            $sql .= " AND DATE(r.post_date) >= '$fromDate'";
        }
        if (!empty($toDate)) {
            $sql .= " AND DATE(r.post_date) <= '$toDate'";
        }

        $sql .= " ORDER BY r.post_date DESC, r.id DESC LIMIT 100";
        return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function checkDuplicate($table, $trxCode)
    {
        $stmt = $this->db->prepare("SELECT id FROM $table WHERE transaction_code = ?");
        $stmt->bind_param("s", $trxCode);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function createMandiri($data)
    {
        $stmt = $this->db->prepare("INSERT INTO bank_reconciliations (account_no, date, val_date, transaction_code, description1, description2, reference_no, debit, credit, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
        $stmt->bind_param("sssssssddi", $data['account_no'], $data['date'], $data['val_date'], $data['transaction_code'], $data['description1'], $data['description2'], $data['reference_no'], $data['debit'], $data['credit'], $data['created_by']);
        return $stmt->execute();
    }

    public function createCimb($data)
    {
        $stmt = $this->db->prepare("INSERT INTO bank_reconciliations_cimb (line_no, post_date, eff_date, cheque_no, description, debit, credit, balance, transaction_code, reference_no, payment_type, bank_reference, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
        $stmt->bind_param("sssssdddssssi", $data['line_no'], $data['post_date'], $data['eff_date'], $data['cheque_no'], $data['description'], $data['debit'], $data['credit'], $data['balance'], $data['transaction_code'], $data['reference_no'], $data['payment_type'], $data['bank_reference'], $data['created_by']);
        return $stmt->execute();
    }

    public function updateStatus($table, $id, $status, $note, $userId, $userField = 'updated_by')
    {
        $stmt = $this->db->prepare("UPDATE $table SET status = ?, note = ?, $userField = ?, updated_at = NOW() WHERE id = ?");
        
        if ($table === 'bank_reconciliations') {
            $userVal = $userId; 
            $stmt->bind_param("sssi", $status, $note, $userVal, $id); 
        } else {
            $stmt->bind_param("ssii", $status, $note, $userId, $id);
        }
        
        return $stmt->execute();
    }

    public function updateMandiri($id, $data)
    {
        $stmt = $this->db->prepare("UPDATE bank_reconciliations SET account_no=?, date=?, val_date=?, transaction_code=?, description1=?, description2=?, reference_no=?, debit=?, credit=? WHERE id=?");
        $stmt->bind_param("sssssssddi", $data['account_no'], $data['date'], $data['val_date'], $data['transaction_code'], $data['description1'], $data['description2'], $data['reference_no'], $data['debit'], $data['credit'], $id);
        return $stmt->execute();
    }

    public function delete($table, $id)
    {
        $stmt = $this->db->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getById($table, $id)
    {
        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function addFile($table, $rekonId, $fileName, $filePath, $fileType)
    {
        $stmt = $this->db->prepare("INSERT INTO $table (rekon_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $rekonId, $fileName, $filePath, $fileType);
        return $stmt->execute();
    }

    public function getSummary($table)
    {
        $sql = "SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM $table";
        return $this->db->query($sql)->fetch_assoc();
    }

    public function getStatusCounts($table)
    {
        $sql = "SELECT status, COUNT(*) as count FROM $table GROUP BY status";
        $result = $this->db->query($sql);
        $statuses = ['New' => 0, 'Approved' => 0, 'Rejected' => 0];
        while($row = $result->fetch_assoc()) {
            $statuses[$row['status']] = (int)$row['count'];
        }
        return $statuses;
    }

    public function getRecent($table)
    {
        return $this->db->query("SELECT * FROM $table ORDER BY updated_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    }
}