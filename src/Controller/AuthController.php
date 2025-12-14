<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\UserService;

class AuthController
{
    private $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function login()
    {
        $input = Input::json();
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            Response::json(['message' => 'Username dan Password wajib diisi'], 200, false);
        }

        $user = $this->userService->authenticate($username, $password);

        if ($user) {
            Response::json($user, 200, true);
        } else {
            Response::json([], 200, false); 
        }
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $result = $this->userService->register($input);
        
        Response::json(['message' => $result['message']], 200, $result['success']);
    }
}