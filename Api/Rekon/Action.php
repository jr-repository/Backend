<?php
require_once __DIR__ . '/../../autoload.php';

use App\Controller\RekonController;
use App\Core\Response;

// Tambahkan try-catch untuk menangkap setiap Exception/Throwable
try {
    // Panggil Controller yang sudah mengandung logic service dan repository
    (new RekonController())->action();
} catch (\Throwable $e) {
    // Tangkap error fatal (Database/PHP crash) dan berikan respons JSON yang aman
    // Ini menggantikan respons 500 polos menjadi respons JSON terstruktur.
    Response::error('Critical Connection Error: ' . $e->getMessage(), 500);
}