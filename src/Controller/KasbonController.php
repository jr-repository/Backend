<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\KasbonService;

class KasbonController
{
    private $service;

    public function __construct()
    {
        $this->service = new KasbonService();
    }

    public function list()
    {
        $q = $_GET['q'] ?? '';
        $status = $_GET['status'] ?? '';
        $data = $this->service->getList($q, $status);
        Response::json($data, 200, true);
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        if (empty($input['bankId']) || empty($input['transDate']) || empty($input['detailAccount'])) {
            Response::json(['message' => 'Data tidak lengkap'], 400, false);
        }
        
        $result = $this->service->save($input, $input['user_id'] ?? 0, $input['user_name'] ?? 'System');
        if ($result['success']) Response::json(['message' => $result['message']], 200, true);
        else Response::json(['message' => $result['message']], 500, false);
    }

    public function update()
    {
        $this->save(); 
    }

    public function detail()
    {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) Response::json(['message' => 'ID required'], 400, false);
        
        $data = $this->service->getDetail($id);
        if ($data) Response::json($data, 200, true);
        else Response::json(['message' => 'Not Found'], 500, false);
    }

    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        $result = $this->service->approve($input['id'], $input['user_id']);
        if ($result['success']) Response::json(['message' => $result['message']], 200, true);
        else Response::error($result['message']);
    }

    public function reject()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        $result = $this->service->reject($input['id'], $input['user_id']);
        if ($result['success']) Response::json(['message' => $result['message']], 200, true);
        else Response::error($result['message']);
    }

    public function dashboardSummary()
    {
        $start = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
        $end = $_GET['end_date'] ?? date('Y-m-d');
        $data = $this->service->getDashboardSummary($start, $end);
        Response::json($data, 200, true);
    }

    public function getJoExpenses()
    {
        $joNo = $_GET['jo_number'] ?? '';
        if (empty($joNo)) Response::json(['message' => 'JO Number required'], 400, false);
        
        $data = $this->service->getJoExpenses($joNo);
        if ($data) Response::json($data, 200, true);
        else Response::json(['message' => 'Job Order not found'], 500, false);
    }

    public function masterGlAccount()
    {
        $q = $_GET['q'] ?? '';
        $type = strtoupper($_GET['type'] ?? '');
        $data = (new \App\Service\TransferService())->getGlAccounts($q, $type);
        Response::json($data['d'], 200, true);
    }

    public function export()
    {
        require_once __DIR__ . '/../../Api/Kasbon/Export.php';
    }

    public function exportJoExcel()
    {
        require_once __DIR__ . '/../../Api/Kasbon/ExportJoExcel.php';
    }

    public function exportJoPdf()
    {
        require_once __DIR__ . '/../../Api/Kasbon/ExportJoPdf.php';
    }
}