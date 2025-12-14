<?php
require_once __DIR__ . '/../../autoload.php';

use App\Controller\ApproverController;
use App\Core\Response;

try {
    (new ApproverController())->update();
} catch (\Throwable $e) {
    Response::error('Critical Error: ' . $e->getMessage(), 500);
}