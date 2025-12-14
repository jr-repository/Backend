<?php
namespace App\Repository;

use App\Core\Database;

class NotificationRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function countPendingKasbon()
    {
        $res = $this->db->query("SELECT COUNT(*) as total FROM kasbon_transactions WHERE status = 'WAITING_APPROVAL'");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    public function countPendingInvoice()
    {
        $res = $this->db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'WAITING_APPROVAL'");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    public function countPendingBill()
    {
        $res = $this->db->query("SELECT COUNT(*) as total FROM bills WHERE status = 'WAITING_APPROVAL'");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    public function countPendingRekonMandiri()
    {
        $res = $this->db->query("SELECT COUNT(*) as total FROM bank_reconciliations WHERE status = 'WAITING_APPROVAL'");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    public function countPendingRekonCimb()
    {
        $res = $this->db->query("SELECT COUNT(*) as total FROM bank_reconciliations_cimb WHERE status = 'WAITING_APPROVAL'");
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }
}