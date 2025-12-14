<?php
require_once __DIR__ . '/autoload.php';

use App\Core\Database;
use App\Core\Response;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    Response::error('Database Connection Error', 500);
}