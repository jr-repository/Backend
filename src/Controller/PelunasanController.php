<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\PelunasanService;

class PelunasanController
{
    private $service;

    public function __construct()
    {
        $this->service = new PelunasanService();
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        if (empty($input['customerNo']) || empty($input['bankId']) || empty($input['invoices'])) {
            Response::json(['message' => 'Data tidak lengkap'], 400, false);
        }

        $response = $this->service->save($input);
        header('Content-Type: application/json');
        echo $response;
        exit;
    }

    public function masterInvoice()
    {
        $customerNo = $_GET['customerNo'] ?? '';
        $q = $_GET['q'] ?? '';
        $data = $this->service->getOutstandingInvoices($customerNo, $q);
        Response::json($data, 200, true);
    }

    public function masterCustomer()
    {
        $data = $this->service->getCustomers();
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } else {
            Response::json(['s' => false, 'd' => []]);
        }
    }

    public function masterGlAccount()
    {
        $q = $_GET['q'] ?? '';
        $type = strtoupper($_GET['type'] ?? '');
        $data = (new \App\Service\TransferService())->getGlAccounts($q, $type);
        Response::json($data['d'], 200, true);
    }
}