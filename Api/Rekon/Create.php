<?php
require_once __DIR__ . '/../../autoload.php';

use App\Core\Database;
use App\Core\Response;
use App\Security\Input;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = Input::json(); // Gunakan sanitasi input

// Validasi Input Wajib
if (empty($input['account_no']) || empty($input['transaction_code']) || empty($input['date'])) {
    Response::error('Data wajib (Account, Trx Code, Date) harus diisi', 400);
}

try {
    $conn = Database::getInstance()->getConnection();
    
    $accNo = $input['account_no'];
    // Input::json() sudah meng-sanitize, tapi kita perlu format tanggal kembali
    $date = date('Y-m-d', strtotime($input['date']));
    $valDate = date('Y-m-d', strtotime($input['val_date'] ?? $input['date']));
    $trxCode = $input['transaction_code'];
    $desc1 = $input['description1'] ?? '';
    $desc2 = $input['description2'] ?? '';
    $refNo = $input['reference_no'] ?? '';
    $debit = floatval($input['debit'] ?? 0);
    $credit = floatval($input['credit'] ?? 0);
    $createdBy = $input['created_by'] ?? NULL;

    // 1. Cek Duplikat Transaction Code (Prepared Statement)
    $check = $conn->prepare("SELECT id FROM bank_reconciliations WHERE transaction_code = ?");
    $check->bind_param("s", $trxCode);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        throw new \Exception("Transaction Code '$trxCode' sudah ada di sistem.");
    }

    // 2. Insert Data
    $stmt = $conn->prepare("INSERT INTO bank_reconciliations 
        (account_no, date, val_date, transaction_code, description1, description2, reference_no, debit, credit, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");
    
    // Bind parameter: ssssssddsi
    $stmt->bind_param("sssssssddi", $accNo, $date, $valDate, $trxCode, $desc1, $desc2, $refNo, $debit, $credit, $createdBy);

    if ($stmt->execute()) {
        Response::json(['message' => 'Transaksi berhasil ditambahkan manual'], 200, true);
    } else {
        throw new \Exception("Gagal menyimpan ke database: " . $stmt->error);
    }

} catch (\Throwable $e) {
    Response::error('Error: ' . $e->getMessage(), 500);
}