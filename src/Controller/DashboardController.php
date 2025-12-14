<?php
namespace App\Controller;

use App\Core\Response;
use App\Service\DashboardService;

date_default_timezone_set('Asia/Jakarta');

class DashboardController
{
    private $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function getData()
    {
        $fromDate = $_GET['fromDate'] ?? date('01/m/Y');
        $toDate = $_GET['toDate'] ?? date('d/m/Y', strtotime('-1 day'));

        $data = $this->service->getDashboardData($fromDate, $toDate);
        Response::json($data, 200, true);
    }
}
