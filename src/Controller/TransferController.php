<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\TransferService;

class TransferController
{
    private $service;

    public function __construct()
    {
        $this->service = new TransferService();
    }

    public function list()
    {
        $q = $_GET['q'] ?? '';
        $data = $this->service->getList($q);
        Response::json($data, 200, true);
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        if (empty($input['fromBankId']) || empty($input['toBankId']) || empty($input['amount'])) {
            Response::json(['message' => 'Data tidak lengkap'], 400, false);
        }
        
        $userId = $input['user_id'] ?? 0;
        $userName = $input['user_name'] ?? 'System';
        
        $response = $this->service->save($input, $userId, $userName);
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    public function detail()
    {
        $id = $_GET['id'] ?? '';
        if (empty($id)) Response::json(['message' => 'ID wajib'], 400, false);
        
        $res = $this->service->getDetail($id);
        header('Content-Type: application/json');
        echo $res;
        exit;
    }

    public function masterGlAccount()
    {
        $q = $_GET['q'] ?? '';
        $type = strtoupper($_GET['type'] ?? '');
        $data = $this->service->getGlAccounts($q, $type);
        Response::json($data['d'], 200, true);
    }
}