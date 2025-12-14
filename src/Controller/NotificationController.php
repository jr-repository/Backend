<?php
namespace App\Controller;

use App\Core\Response;
use App\Service\NotificationService;

class NotificationController
{
    private $service;

    public function __construct()
    {
        $this->service = new NotificationService();
    }

    public function getPending()
    {
        $userId = $_GET['user_id'] ?? 0;
        if (empty($userId)) {
            Response::json(['message' => 'User ID required'], 400, false);
        }

        $data = $this->service->getPendingNotifications($userId);
        Response::json($data, 200, true);
    }
}