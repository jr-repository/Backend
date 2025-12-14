<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\PenerimaanService;

class PenerimaanController
{
    private $service;

    public function __construct()
    {
        $this->service = new PenerimaanService();
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
        if (empty($input['bankId']) || empty($input['detailAccount'])) {
            Response::json(['message' => 'Data tidak lengkap'], 400, false);
        }
        
        $res = $this->service->save($input, $input['user_id'] ?? 0, $input['user_name'] ?? 'System');
        header('Content-Type: application/json');
        echo $res;
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
        $data = (new \App\Service\TransferService())->getGlAccounts($q, $type);
        Response::json($data['d'], 200, true);
    }
}