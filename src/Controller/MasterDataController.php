<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\MasterDataService;

class MasterDataController
{
    private $service;

    public function __construct()
    {
        $this->service = new MasterDataService();
    }

    public function listTaxes()
    {
        $data = $this->service->getTaxes();
        Response::json($data, 200, true);
    }

    public function saveTax()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $result = $this->service->saveTax($input);
        
        if ($result['success']) {
            Response::json(['message' => $result['message']], 200, true);
        } else {
            Response::json(['message' => $result['message']], 500, false);
        }
    }

    public function deleteTax()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $id = $input['id'] ?? 0;
        
        $result = $this->service->deleteTax($id);
        
        if ($result['success']) {
            Response::json(['message' => $result['message']], 200, true);
        } else {
            Response::json(['message' => $result['message']], 500, false);
        }
    }
}