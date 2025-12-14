<?php
namespace App\Service;

use App\Core\Database;
use App\Service\AccurateClient;

class ReportService
{
    private $db;
    private $accurate;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->accurate = new AccurateClient();
    }

    public function getUserActivity($startDate, $endDate, $userId = '')
    {
        $whereClause = "WHERE DATE(created_at) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        $types = "ss";

        if (!empty($userId) && $userId !== 'ALL') {
            $whereClause .= " AND user_id = ?";
            $params[] = intval($userId);
            $types .= "i";
        }

        $sqlLogs = "SELECT * FROM system_activity_logs $whereClause ORDER BY created_at DESC";
        $stmtLogs = $this->db->prepare($sqlLogs);
        $stmtLogs->bind_param($types, ...$params);
        $stmtLogs->execute();
        $logs = $stmtLogs->get_result()->fetch_all(MYSQLI_ASSOC);

        $sqlSummary = "SELECT COUNT(*) as total_actions, SUM(transaction_amount) as total_value FROM system_activity_logs $whereClause";
        $stmtSum = $this->db->prepare($sqlSummary);
        $stmtSum->bind_param($types, ...$params);
        $stmtSum->execute();
        $summary = $stmtSum->get_result()->fetch_assoc();

        $sqlTop = "SELECT user_name, COUNT(*) as action_count, SUM(transaction_amount) as total_value 
                   FROM system_activity_logs 
                   $whereClause
                   GROUP BY user_id, user_name 
                   ORDER BY action_count DESC 
                   LIMIT 5";
        $stmtTop = $this->db->prepare($sqlTop);
        $stmtTop->bind_param($types, ...$params);
        $stmtTop->execute();
        $topUsers = $stmtTop->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'logs' => $logs,
            'summary' => $summary,
            'top_users' => $topUsers
        ];
    }

    public function getProfitLoss($fromDate, $toDate)
    {
        return $this->accurate->call('/glaccount/get-pl-account-amount.do', 'GET', [
            'fromDate' => $fromDate,
            'toDate' => $toDate
        ]);
    }
}