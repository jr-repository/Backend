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
    die("Error: Library Vendor (Composer) tidak ditemukan.");
}
// ----------------------------------------------------

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'A1' => 'Account No', 
        'B1' => 'Date (YYYY-MM-DD)', 
        'C1' => 'Val. Date (YYYY-MM-DD)', 
        'D1' => 'Transaction Code (Unique)', 
        'E1' => 'Description 1', 
        'F1' => 'Description 2', 
        'G1' => 'Reference No', 
        'H1' => 'Debit', 
        'I1' => 'Credit'
    ];

    foreach ($headers as $cell => $val) {
        $sheet->setCellValue($cell, $val);
    }

    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $sheet->setCellValue('A2', '1234567890');
    $sheet->setCellValue('B2', date('Y-m-d'));
    $sheet->setCellValue('C2', date('Y-m-d'));
    $sheet->setCellValue('D2', 'TRX-' . rand(1000,9999));
    $sheet->setCellValue('E2', 'Setoran Awal');
    $sheet->setCellValue('F2', 'Cabang Jakarta');
    $sheet->setCellValue('G2', 'REF001');
    $sheet->setCellValue('H2', 1000000);
    $sheet->setCellValue('I2', 0);

    $sheet->getStyle('H2:I2')->getNumberFormat()->setFormatCode('#,##0.00');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_rekon_bank.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo "Error generating excel: " . $e->getMessage();
}