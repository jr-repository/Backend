<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\UserController;
(new UserController())->updateAccess();