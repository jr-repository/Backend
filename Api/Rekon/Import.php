<?php
require_once __DIR__ . '/../../autoload.php';

// --- Composer Autoloader Check (Wajib untuk PhpSpreadsheet) ---
$vendorPath1 = __DIR__ . '/../../vendor/autoload.php';
$vendorPath2 = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($vendorPath1)) {
    require_once $vendorPath1;
} elseif (file_exists($vendorPath2)) {
    require_once $vendorPath2;
} else {
    // Karena ini file import, biarkan kode Composer check tetap ada
}
// ----------------------------------------------------

// Gunakan kelas dari arsitektur baru
use App\Core\Database;
use App\Core\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if (!isset($_FILES['file'])) {
    Response::error('File Excel wajib diupload', 400);
}

$createdBy = $_POST['user_id'] ?? NULL; 

try {
    $conn = Database::getInstance()->getConnection();
    $file = $_FILES['file']['tmp_name'];
    
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $successCount = 0;
    $failCount = 0;
    $errors = [];

    // Gunakan transaksi untuk batch insert
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("INSERT INTO bank_reconciliations 
        (account_no, date, val_date, transaction_code, description1, description2, reference_no, debit, credit, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'New', ?)");

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        if (empty($row[0]) && empty($row[3])) continue;

        $accNo = $row[0] ?? '';
        
        $date = $row[1] ?? date('Y-m-d');
        if (is_numeric($date)) {
            $date = Date::excelToDateTimeObject($date)->format('Y-m-d');
        }
        
        $valDate = $row[2] ?? date('Y-m-d');
        if (is_numeric($valDate)) {
            $valDate = Date::excelToDateTimeObject($valDate)->format('Y-m-d');
        }

        $trxCode = $row[3] ?? '';
        $desc1 = $row[4] ?? '';
        $desc2 = $row[5] ?? '';
        $refNo = $row[6] ?? '';
        $debit = floatval($row[7] ?? 0);
        $credit = floatval($row[8] ?? 0);

        if (empty($trxCode)) {
            $failCount++;
            continue;
        }

        // Cek Duplikat di dalam loop (untuk batch insert)
        $check = $conn->query("SELECT id FROM bank_reconciliations WHERE transaction_code = '$trxCode'");
        if ($check->num_rows > 0) {
            $failCount++;
            $errors[] = "Trx Code $trxCode sudah ada (Baris " . ($i+1) . ")";
            continue;
        }

        // Bind parameter: ssssssddsi
        $stmt->bind_param("sssssssddi", $accNo, $date, $valDate, $trxCode, $desc1, $desc2, $refNo, $debit, $credit, $createdBy);
        
        if ($stmt->execute()) {
            $successCount++;
        } else {
            $failCount++;
            $errors[] = "Gagal insert $trxCode: " . $stmt->error;
        }
    }
    
    $conn->commit();

    Response::json([
        'message' => "Import Excel Selesai. Sukses: $successCount, Gagal/Duplikat: $failCount",
        'errors' => $errors
    ], 200, true);

} catch (\Throwable $e) {
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    Response::error("Gagal memproses file Excel. Pesan sistem: " . $e->getMessage(), 500);
}