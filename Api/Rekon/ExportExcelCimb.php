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
    // Biarkan script tetap berjalan jika Autoload missing, tapi akan error di class Spreadsheet
}
// ----------------------------------------------------

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Core\Database; 

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $conn = Database::getInstance()->getConnection(); 

    $fromDate = $_GET['from_date'] ?? '';
    $toDate = $_GET['to_date'] ?? '';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rekon CIMB');

    $sqlData = "SELECT r.*, uc.name as creator_name, uu.name as updater_name 
                FROM bank_reconciliations_cimb r 
                LEFT JOIN users uc ON r.created_by = uc.id
                LEFT JOIN users uu ON r.updated_by = uu.id
                WHERE 1=1";
    
    $sqlSummary = "SELECT SUM(r.debit) as total_debit, SUM(r.credit) as total_credit FROM bank_reconciliations_cimb r WHERE 1=1";
    
    $params = [];
    $types = "";

    if (!empty($fromDate)) {
        $sqlData .= " AND DATE(r.`post_date`) >= ?";
        $sqlSummary .= " AND DATE(r.`post_date`) >= ?";
        $params[] = $fromDate;
        $types .= "s";
    }
    if (!empty($toDate)) {
        $sqlData .= " AND DATE(r.`post_date`) <= ?";
        $sqlSummary .= " AND DATE(r.`post_date`) <= ?";
        $params[] = $toDate;
        $types .= "s";
    }

    $sqlData .= " ORDER BY r.post_date ASC";

    $stmtSummary = $conn->prepare($sqlSummary);
    if (!empty($types)) {
        $stmtSummary->bind_param($types, ...$params);
    }
    $stmtSummary->execute();
    $resultSummary = $stmtSummary->get_result()->fetch_assoc();

    $stmtData = $conn->prepare($sqlData);
    if (!empty($types)) {
        $stmtData->bind_param($types, ...$params);
    }
    $stmtData->execute();
    $resultData = $stmtData->get_result();

    $summaryRow = 1;
    $sheet->setCellValue('A' . $summaryRow, 'REKONSILIASI BANK CIMB NIAGA');
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $summaryRow++;
    $sheet->setCellValue('A' . $summaryRow, 'Periode Filter:');
    $sheet->setCellValue('B' . $summaryRow, ($fromDate ?: 'Awal') . ' s/d ' . ($toDate ?: 'Sekarang'));
    
    $summaryRow++;
    $sheet->setCellValue('A' . $summaryRow, 'Total Debit:');
    $sheet->setCellValue('B' . $summaryRow, $resultSummary['total_debit'] ?? 0);
    $sheet->getStyle('B' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A' . $summaryRow . ':B' . $summaryRow)->getFont()->setBold(true);
    
    $summaryRow++;
    $sheet->setCellValue('A' . $summaryRow, 'Total Credit:');
    $sheet->setCellValue('B' . $summaryRow, $resultSummary['total_credit'] ?? 0);
    $sheet->getStyle('B' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A' . $summaryRow . ':B' . $summaryRow)->getFont()->setBold(true);

    $startDataRow = $summaryRow + 2; 

    $headers = [
        'No', 'Post Date', 'Eff Date', 'Cheque no', 'Description', 'Debit', 'Credit', 
        'Balance', 'Transaction Code', 'Ref no', 'Payment Type', 'Bank Reference', 
        'Status', 'Note', 'Created By', 'Approved By'
    ];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $startDataRow, $header);
        $col++;
    }

    $headerRange = 'A' . $startDataRow . ':' . chr(ord('A') + count($headers) - 1) . $startDataRow;
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1677FF']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    $rowNum = $startDataRow + 1;
    while ($row = $resultData->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowNum, $row['line_no']);
        $sheet->setCellValue('B' . $rowNum, $row['post_date']);
        $sheet->setCellValue('C' . $rowNum, $row['eff_date']);
        $sheet->setCellValue('D' . $rowNum, $row['cheque_no']);
        $sheet->setCellValue('E' . $rowNum, $row['description']);
        $sheet->setCellValue('F' . $rowNum, $row['debit']);
        $sheet->setCellValue('G' . $rowNum, $row['credit']);
        $sheet->setCellValue('H' . $rowNum, $row['balance']);
        $sheet->setCellValue('I' . $rowNum, $row['transaction_code']);
        $sheet->setCellValue('J' . $rowNum, $row['reference_no']);
        $sheet->setCellValue('K' . $rowNum, $row['payment_type']);
        $sheet->setCellValue('L' . $rowNum, $row['bank_reference']);
        $sheet->setCellValue('M' . $rowNum, $row['status']);
        $sheet->setCellValue('N' . $rowNum, $row['note']);
        $sheet->setCellValue('O' . $rowNum, $row['creator_name']);
        $sheet->setCellValue('P' . $rowNum, $row['updater_name']);
        $sheet->getStyle('F' . $rowNum . ':G' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('H' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $rowNum++;
    }

    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rekon_cimb_export_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo "Fatal Error Export CIMB:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit;
}