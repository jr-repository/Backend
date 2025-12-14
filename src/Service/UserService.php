<?php
namespace App\Service;

use App\Repository\UserRepository;

class UserService
{
    private $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    public function authenticate($username, $password)
    {
        $user = $this->userRepo->findByUsername($username);
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $user['permissions'] = $this->userRepo->getUserPermissions($user['id']);
            $user['approvals'] = $this->userRepo->getUserApprovals($user['id']);
            return $user;
        }
        return null;
    }

    public function register($data)
    {
        if (empty($data['username']) || empty($data['password']) || empty($data['name'])) {
            return ['success' => false, 'message' => 'Data tidak lengkap'];
        }

        if ($this->userRepo->exists($data['username'])) {
            return ['success' => false, 'message' => 'Username sudah digunakan'];
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role'] ?? 'user';

        if ($this->userRepo->create($data['username'], $hashedPassword, $data['name'], $role)) {
            return ['success' => true, 'message' => 'User berhasil ditambahkan'];
        }

        return ['success' => false, 'message' => 'Gagal menambah user'];
    }

    public function getAllUsersWithPermissions()
    {
        $users = $this->userRepo->findAll();
        $allPermissions = $this->userRepo->getAllPermissions();
        $allApprovals = $this->userRepo->getApprovalsForAllUsers();

        foreach ($users as &$user) {
            $userId = $user['id'];
            $user['permissions'] = $allPermissions[$userId] ?? [];
            
            $userApprovals = $allApprovals[$userId] ?? [];
            $formattedApprovals = [
                'KASBON' => $userApprovals['KASBON'] ?? false,
                'INVOICE' => $userApprovals['INVOICE'] ?? false,
                'BILL' => $userApprovals['BILL'] ?? false,
                'REKON' => $userApprovals['REKON'] ?? false
            ];
            
            $user['approvals'] = $formattedApprovals; 
        }

        return $users;
    }

    public function deleteUser($id)
    {
        return $this->userRepo->delete($id);
    }

    public function updatePermissions($userId, $menus)
    {
        return $this->userRepo->updateUserPermissions($userId, $menus);
    }
}