<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\NotificationController;
(new NotificationController())->getPending();