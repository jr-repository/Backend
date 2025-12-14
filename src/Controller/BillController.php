<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\BillService;

class BillController
{
    private $service;

    public function __construct()
    {
        $this->service = new BillService();
    }

    public function list()
    {
        $q = $_GET['q'] ?? '';
        $data = $this->service->getList($q);
        Response::json($data, 200, true);
    }

    public function detail()
    {
        $id = $_GET['id'] ?? 0;
        if (empty($id)) {
            Response::json(['message' => 'ID required'], 200, false);
        }
        $data = $this->service->getDetail($id);
        if ($data) {
            Response::json($data, 200, true);
        } else {
            Response::json(['message' => 'Data not found'], 500, false);
        }
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $input = Input::json();
        $userId = $input['user_id'] ?? 0;
        $userName = $input['user_name'] ?? 'System';

        if (empty($input['vendorNo']) || empty($input['items'])) {
            Response::json(['message' => 'Data Vendor dan Item wajib diisi'], 400, false);
        }

        $result = $this->service->saveTransaction($input, $userId, $userName);
        
        if ($result['success']) {
            Response::json($result, 200, true);
        } else {
            Response::json($result, 500, false);
        }
    }

    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $id = $input['id'] ?? 0;
        $userId = $input['user_id'] ?? 0;

        if (empty($id)) {
            Response::json(['message' => 'Invalid Request'], 400, false);
        }

        $result = $this->service->approve($id, $userId);
        
        if ($result['success']) {
            Response::json(['message' => $result['message']], 200, true);
        } else {
            Response::error($result['message']);
        }
    }

    public function reject()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $id = $input['id'] ?? 0;
        $userId = $input['user_id'] ?? 0;

        $result = $this->service->reject($id, $userId);
        
        if ($result['success']) {
            Response::json(['message' => $result['message']], 200, true);
        } else {
            Response::error($result['message']);
        }
    }

    public function masterVendor()
    {
        $data = $this->service->getMasterVendors();
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode(['s' => true, 'd' => $data['d']]);
            exit;
        } else {
            Response::json(['s' => false, 'd' => []]);
        }
    }

    public function masterItem()
    {
        $data = (new \App\Service\InvoiceService())->getMasterItems();
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } else {
            Response::error('Failed to fetch items');
        }
    }
}