<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\AuthController;
(new AuthController())->register();