<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\UserService;

class UserController
{
    private $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function list()
    {
        $users = $this->userService->getAllUsersWithPermissions();
        Response::json($users, 200, true);
    }

    public function delete()
    {
        $input = Input::json();
        $id = $input['id'] ?? 0;

        if (empty($id)) {
            Response::json(['message' => 'ID wajib diisi'], 400, false);
        }

        if ($this->userService->deleteUser($id)) {
            Response::json(['message' => 'User berhasil dihapus'], 200, true);
        } else {
            Response::error('Gagal menghapus user');
        }
    }

    public function updateAccess()
    {
        $input = Input::json();
        $userId = $input['user_id'] ?? 0;
        $menus = $input['menus'] ?? [];

        if (empty($userId)) {
            Response::json(['message' => 'User ID wajib diisi'], 400, false);
        }

        try {
            $this->userService->updatePermissions($userId, $menus);
            Response::json(['message' => 'Hak akses berhasil diperbarui'], 200, true);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}