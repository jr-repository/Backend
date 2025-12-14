<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\InvoiceController;
(new InvoiceController())->list();