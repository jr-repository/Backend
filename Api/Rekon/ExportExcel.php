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
    // Karena ini file export, biarkan kode Composer check tetap ada
}
// ----------------------------------------------------

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Menggunakan global $conn dari Database.php (sesuai legacy code)
require_once __DIR__ . '/../../Database.php';


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $fromDate = $_GET['from_date'] ?? '';
    $toDate = $_GET['to_date'] ?? '';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rekon Mandiri');

    // --- 1. Ambil Data dan Summary ---
    $sqlData = "SELECT r.*, uc.name as creator_name 
                FROM bank_reconciliations r 
                LEFT JOIN users uc ON r.created_by = uc.id
                WHERE 1=1";
    
    $sqlSummary = "SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM bank_reconciliations WHERE 1=1";

    if (!empty($fromDate)) {
        $sqlData .= " AND DATE(r.val_date) >= '$fromDate'";
        $sqlSummary .= " AND DATE(val_date) >= '$fromDate'";
    }
    if (!empty($toDate)) {
        $sqlData .= " AND DATE(r.val_date) <= '$toDate'";
        $sqlSummary .= " AND DATE(val_date) <= '$toDate'";
    }

    $sqlData .= " ORDER BY r.date ASC";

    $resultData = $conn->query($sqlData);
    $resultSummary = $conn->query($sqlSummary)->fetch_assoc();

    // --- 2. Tulis Summary di Baris Awal ---
    $summaryRow = 1;
    $sheet->setCellValue('A' . $summaryRow, 'REKONSILIASI BANK MANDIRI (MML)');
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

    // --- 3. Tulis Header Kolom ---
    $headers = [
        'Account No', 'Date', 'Val. Date', 'Transaction Code', 'Description 1', 'Description 2', 'Reference No', 
        'Debit', 'Credit', 'Status', 'Note', 'Created By', 'Updated By'
    ];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $startDataRow, $header);
        $sheet->getStyle($col . $startDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $col++;
    }

    // Styling Header
    $headerRange = 'A' . $startDataRow . ':' . chr(ord('A') + count($headers) - 1) . $startDataRow;
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1677FF']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // --- 4. Tulis Data Transaksi ---
    $rowNum = $startDataRow + 1;
    while ($row = $resultData->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowNum, $row['account_no']);
        $sheet->setCellValue('B' . $rowNum, $row['date']);
        $sheet->setCellValue('C' . $rowNum, $row['val_date']);
        $sheet->setCellValue('D' . $rowNum, $row['transaction_code']);
        $sheet->setCellValue('E' . $rowNum, $row['description1']);
        $sheet->setCellValue('F' . $rowNum, $row['description2']);
        $sheet->setCellValue('G' . $rowNum, $row['reference_no']);
        $sheet->setCellValue('H' . $rowNum, $row['debit']);
        $sheet->setCellValue('I' . $rowNum, $row['credit']);
        $sheet->setCellValue('J' . $rowNum, $row['status']);
        $sheet->setCellValue('K' . $rowNum, $row['note']);
        $sheet->setCellValue('L' . $rowNum, $row['creator_name'] ?? '-');
        $sheet->setCellValue('M' . $rowNum, $row['updated_by'] ?? '-');
        
        $sheet->getStyle('H' . $rowNum . ':I' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $rowNum++;
    }

    foreach (range('A', 'M') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- 5. Output file ---
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rekon_mandiri_export_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo "Error generating excel: " . $e->getMessage();
}