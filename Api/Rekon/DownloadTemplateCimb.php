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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template CIMB');

    $headers = [
        'A8' => 'No', 
        'B8' => 'Post Date', 
        'C8' => 'Value Date',
        'D8' => 'Effective Date',
        'E8' => 'Cheque no', 
        'F8' => 'Description', 
        'G8' => 'Debit', 
        'H8' => 'Credit', 
        'I8' => 'Reference',
        'J8' => 'Balance',
        'K8' => 'Transaction',
        'L8' => 'Ref no',
        'M8' => 'Payment Type',
        'N8' => 'Bank Reference'
    ];

    foreach ($headers as $cell => $val) {
        $sheet->setCellValue($cell, $val);
    }

    $sheet->getStyle('A8:N8')->getFont()->setBold(true);
    
    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $dateNow = new DateTime();
    $sheet->setCellValue('A9', 1);
    $sheet->setCellValue('B9', $dateNow->format('m/d/Y H:i:s')); 
    $sheet->setCellValue('C9', ''); 
    $sheet->setCellValue('D9', $dateNow->format('m/d/Y H:i:s'));
    $sheet->setCellValue('E9', 'CHK-123'); 
    $sheet->setCellValue('F9', 'Pembayaran Biaya Server');
    $sheet->setCellValue('G9', 500000);
    $sheet->setCellValue('H9', 0);
    $sheet->setCellValue('I9', '');
    $sheet->setCellValue('J9', 15000000);
    $sheet->setCellValue('K9', 'TRX-CIMB-' . rand(1000,9999));
    $sheet->setCellValue('L9', 'REF-001');
    $sheet->setCellValue('M9', 'Transfer');
    $sheet->setCellValue('N9', 'BANKREF123');

    $sheet->getStyle('G9:H9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
    $sheet->getStyle('J9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

    $dateFormat = NumberFormat::FORMAT_DATE_DATETIME;
    $sheet->getStyle('B9')->getNumberFormat()->setFormatCode($dateFormat);
    $sheet->getStyle('D9')->getNumberFormat()->setFormatCode($dateFormat);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="template_rekon_cimb.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    echo "Fatal Error Template CIMB:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit;
}