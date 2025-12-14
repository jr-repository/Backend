<?php
namespace App\Controller;

use App\Core\Response;
use App\Security\Input;
use App\Service\SettingsService;

class SettingsController
{
    private $service;

    public function __construct()
    {
        $this->service = new SettingsService();
    }

    public function get()
    {
        $data = $this->service->getSettings();
        Response::json($data, 200, true);
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $input = Input::json();
        $result = $this->service->saveSettings($input);
        
        if ($result['success']) {
            Response::json(['message' => $result['message']], 200, true);
        } else {
            Response::json(['message' => $result['message']], 400, false);
        }
    }
}