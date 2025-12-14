<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\UserService;
use App\Core\Database;

class ApproverController
{
    private $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function list()
    {
        $data = $this->userService->getAllUsersWithPermissions();
        Response::json($data, 200, true);
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        
        $input = Input::json();
        $userId = $input['user_id'] ?? 0;
        $module = $input['module'] ?? '';
        $val = $input['value'] ?? false;
        
        if (empty($userId) || empty($module)) {
            Response::json(['message' => 'Data tidak lengkap'], 400, false);
        }

        $canApprove = ($val === true || $val === 1 || $val === '1' || $val === 'true') ? 1 : 0;
        
        $db = Database::getInstance()->getConnection();
        
        try {
            $stmt = $db->prepare("INSERT INTO approval_permissions (user_id, module, can_approve) VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE can_approve = ?");
            
            $stmt->bind_param("isii", $userId, $module, $canApprove, $canApprove);
            
            if ($stmt->execute()) {
                Response::json(['message' => 'Hak approval diperbarui'], 200, true);
            } else {
                throw new \Exception("Database error: " . $stmt->error);
            }

        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}