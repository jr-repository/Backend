<?php
namespace App\Repository;

use App\Core\Database;

class UserRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function exists($username)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function create($username, $passwordHash, $name, $role)
    {
        $stmt = $this->db->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $passwordHash, $name, $role);
        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function findAll()
    {
        $result = $this->db->query("SELECT id, username, name, role, created_at FROM users ORDER BY name ASC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getAllPermissions()
    {
        $result = $this->db->query("SELECT user_id, menu_key FROM user_permissions");
        $perms = [];
        while ($row = $result->fetch_assoc()) {
            $perms[$row['user_id']][] = $row['menu_key'];
        }
        return $perms;
    }

    public function getUserPermissions($userId)
    {
        $stmt = $this->db->prepare("SELECT menu_key FROM user_permissions WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $perms = [];
        while ($row = $result->fetch_assoc()) {
            $perms[] = $row['menu_key'];
        }
        return $perms;
    }

    public function getUserApprovals($userId)
    {
        $stmt = $this->db->prepare("SELECT module FROM approval_permissions WHERE user_id = ? AND can_approve = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $approvals = [];
        while ($row = $result->fetch_assoc()) {
            $approvals[] = $row['module'];
        }
        return $approvals;
    }

    public function getApprovalsForAllUsers()
    {
        $result = $this->db->query("SELECT user_id, module, can_approve FROM approval_permissions");
        $approvals = [];
        while ($row = $result->fetch_assoc()) {
            $approvals[$row['user_id']][$row['module']] = ($row['can_approve'] == 1);
        }
        return $approvals;
    }

    public function updateUserPermissions($userId, array $menus)
    {
        $this->db->begin_transaction();
        try {
            $del = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $del->bind_param("i", $userId);
            $del->execute();

            if (!empty($menus)) {
                $stmt = $this->db->prepare("INSERT INTO user_permissions (user_id, menu_key) VALUES (?, ?)");
                foreach ($menus as $menuKey) {
                    $stmt->bind_param("is", $userId, $menuKey);
                    $stmt->execute();
                }
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}