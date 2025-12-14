<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\BillController;
(new BillController())->list();