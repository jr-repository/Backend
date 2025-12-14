<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\RekonService;

class RekonController
{
    private $service;

    public function __construct()
    {
        $this->service = new RekonService();
    }

    public function list()
    {
        $q = $_GET['q'] ?? '';
        $status = $_GET['status'] ?? '';
        $data = $this->service->getList('MANDIRI', $q, $status, '', '');
        Response::json($data, 200, true);
    }

    public function listCimb()
    {
        $q = $_GET['q'] ?? '';
        $status = $_GET['status'] ?? '';
        $from = $_GET['from_date'] ?? '';
        $to = $_GET['to_date'] ?? '';
        $data = $this->service->getList('CIMB', $q, $status, $from, $to);
        Response::json($data, 200, true);
    }

    public function create()
    {
        $this->handleCreate('MANDIRI');
    }

    public function createCimb()
    {
        $this->handleCreate('CIMB');
    }

    private function handleCreate($type)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        if (empty($input['transaction_code']) || ($type === 'MANDIRI' && empty($input['date'])) || ($type === 'CIMB' && empty($input['post_date']))) {
            Response::json(['message' => 'Data wajib harus diisi'], 400, false);
        }
        $result = $this->service->createManual($input, $type);
        if ($result['success']) Response::json(['message' => 'Transaksi berhasil ditambahkan manual'], 200, true);
        else Response::json(['message' => $result['message']], 500, false);
    }

    public function import()
    {
        $this->handleImport('MANDIRI');
    }

    public function importCimb()
    {
        $this->handleImport('CIMB');
    }

    private function handleImport($type)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        if (!isset($_FILES['file'])) Response::json(['message' => 'File Excel wajib diupload'], 400, false);
        
        $userId = $_POST['user_id'] ?? 0;
        $result = ($type === 'CIMB') ? $this->service->importCimb($_FILES['file'], $userId) : $this->service->importMandiri($_FILES['file'], $userId);
        
        if ($result['success']) Response::json($result, 200, true);
        else Response::json($result, 500, false);
    }

    public function action()
    {
        $this->handleAction('MANDIRI');
    }

    public function actionCimb()
    {
        $this->handleAction('CIMB');
    }

    private function handleAction($type)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $id = $_POST['id'] ?? '';
        $action = $_POST['action'] ?? '';
        $note = $_POST['note'] ?? '';
        $userId = $_POST['user_id'] ?? 0;
        $files = $_FILES['files'] ?? [];

        if (empty($id)) Response::json(['message' => 'ID wajib diisi'], 400, false);

        $result = $this->service->processAction($id, $action, $note, $userId, $files, $type);
        if ($result['success']) Response::json(['message' => $result['message']], 200, true);
        else Response::json(['message' => $result['message']], 500, false);
    }

    public function delete()
    {
        $this->handleDelete('MANDIRI');
    }

    public function deleteCimb()
    {
        $this->handleDelete('CIMB');
    }

    private function handleDelete($type)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        $id = $input['id'] ?? 0;
        $result = $this->service->deleteTransaction($id, $type);
        if ($result['success']) Response::json(['message' => 'Data dihapus'], 200, true);
        else Response::json(['message' => $result['message']], 500, false);
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        $input = Input::json();
        $id = $input['id'] ?? 0;
        $result = $this->service->updateTransaction($id, $input);
        if ($result['success']) Response::json(['message' => 'Data berhasil diupdate'], 200, true);
        else Response::json(['message' => $result['message']], 500, false);
    }

    public function dashboard()
    {
        $data = $this->service->getDashboardStats('MANDIRI');
        Response::json($data, 200, true);
    }

    public function dashboardCimb()
    {
        $data = $this->service->getDashboardStats('CIMB');
        Response::json($data, 200, true);
    }

    public function downloadTemplate()
    {
        require_once __DIR__ . '/../../Api/Rekon/DownloadTemplate.php'; 
    }

    public function downloadTemplateCimb()
    {
        require_once __DIR__ . '/../../Api/Rekon/DownloadTemplateCimb.php';
    }

    public function exportExcel()
    {
        require_once __DIR__ . '/../../Api/Rekon/ExportExcel.php';
    }

    public function exportExcelCimb()
    {
        require_once __DIR__ . '/../../Api/Rekon/ExportExcelCimb.php';
    }
}