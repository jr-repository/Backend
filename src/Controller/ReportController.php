<?php
namespace App\Controller;

use App\Core\Response;
use App\Service\ReportService;

class ReportController
{
    private $service;

    public function __construct()
    {
        $this->service = new ReportService();
    }

    public function getUserActivity()
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $userId = $_GET['user_id'] ?? '';

        $data = $this->service->getUserActivity($startDate, $endDate, $userId);
        Response::json($data, 200, true);
    }

    public function getProfitLossData()
    {
        $fromDate = $_GET['fromDate'] ?? date('d/m/Y');
        $toDate = $_GET['toDate'] ?? date('d/m/Y');

        $jsonRaw = $this->service->getProfitLoss($fromDate, $toDate);
        
        header('Content-Type: application/json');
        echo $jsonRaw;
        exit;
    }
}