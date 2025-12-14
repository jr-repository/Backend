<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\JobOrderService;

class JobOrderController
{
    private $service;

    public function __construct()
    {
        $this->service = new JobOrderService();
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
        if (empty($input['customerNo']) || empty($input['number']) || empty($input['detailItem'])) {
            Response::json(['message' => 'Data customerNo, number, dan detailItem wajib diisi.'], 400, false);
        }

        $userId = $input['user_id'] ?? 0;
        $userName = $input['user_name'] ?? 'System';

        $response = $this->service->saveTransaction($input, $userId, $userName);
        
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    public function masterCustomer()
    {
        $response = $this->service->getMasterCustomers();
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    public function masterItem()
    {
        $response = $this->service->getMasterItems();
        header('Content-Type: application/json');
        echo $response;
        exit;
    }
}