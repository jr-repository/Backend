<?php
require_once __DIR__ . '/../../autoload.php';

$vendorPath1 = __DIR__ . '/../../vendor/autoload.php';
$vendorPath2 = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($vendorPath1)) {
    require_once $vendorPath1;
} elseif (file_exists($vendorPath2)) {
    require_once $vendorPath2;
} else {

}

use App\Core\Database; 
use App\Core\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    $conn->begin_transaction(); 

    $stmt = $conn->prepare("INSERT INTO bank_reconciliations_cimb 
        (line_no, post_date, eff_date, cheque_no, description, debit, credit, balance, transaction_code, reference_no, payment_type, bank_reference, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new \Exception("Gagal mempersiapkan statement SQL. Error: " . $conn->error);
    }

    $startRowIndex = 8; 

    for ($i = $startRowIndex; $i < count($rows); $i++) {
        $row = $rows[$i];

        if (empty($row[11])) continue; 

        $lineNo = intval($row[0] ?? 0); 
        $postDate = $row[1] ?? date('Y-m-d H:i:s'); 
        if (is_numeric($postDate)) {
            $postDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($postDate)->format('Y-m-d H:i:s');
        } else {
            $postDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $postDate)));
        }

        $effDate = $row[3] ?? $postDate; 
        if (is_numeric($effDate)) {
            $effDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($effDate)->format('Y-m-d H:i:s');
        } else {
            $effDate = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $effDate)));
        }

        $chequeNo = $row[4] ?? ''; 
        $description = $row[5] ?? ''; 
        $debit = floatval(str_replace(',', '', $row[6] ?? 0)); 
        $credit = floatval(str_replace(',', '', $row[7] ?? 0)); 
        $balance = floatval(str_replace(',', '', $row[9] ?? 0)); 
        $trxCode = $row[10] ?? '';
        $refNo = $row[11] ?? ''; 
        $paymentType = $row[12] ?? ''; 
        $bankRef = $row[13] ?? ''; 

        if (empty($trxCode)) {
            $failCount++;
            continue;
        }

        $check = $conn->query("SELECT id FROM bank_reconciliations_cimb WHERE transaction_code = '$trxCode'");
        if ($check->num_rows > 0) {
            $failCount++;
            $errors[] = "Trx Code $trxCode sudah ada (Baris " . ($i+1) . ")";
            continue;
        }

        $stmt->bind_param("issssdddssssi", 
            $lineNo, 
            $postDate, 
            $effDate, 
            $chequeNo, 
            $description, 
            $debit, 
            $credit, 
            $balance, 
            $trxCode, 
            $refNo, 
            $paymentType, 
            $bankRef,
            $createdBy
        );
        
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