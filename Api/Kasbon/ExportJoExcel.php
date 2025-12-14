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
use App\Service\KasbonService;
use App\Core\Response;

try {
    $joNumber = $_GET['jo_number'] ?? '';

    if (empty($joNumber)) {
        die("JO Number required");
    }
    
    $kasbonService = new KasbonService();
    $data = $kasbonService->getJoExpenses($joNumber);
    
    if (!$data || !$data['jo_info']) {
        die("Job Order tidak ditemukan.");
    }

    $jo = $data['jo_info'];
    $expenses = $data['expenses'];
    $totalCost = $data['summary']['total_cost'];
    $totalBill = $data['summary']['total_bill'];
    $grossProfit = $data['summary']['gross_profit'];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('JO Profitability');

    $sheet->setCellValue('A1', 'JOB ORDER PROFITABILITY REPORT');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'JO Number');
    $sheet->setCellValue('B3', ': ' . $jo['transaction_number']);
    $sheet->setCellValue('D3', 'Customer');
    $sheet->setCellValue('E3', ': ' . $jo['customer_name']);

    $sheet->setCellValue('A4', 'Date');
    $sheet->setCellValue('B4', ': ' . date('d/m/Y', strtotime($jo['trans_date'])));
    $sheet->setCellValue('D4', 'PIC');
    $sheet->setCellValue('E4', ': ' . $jo['pic']);

    $sheet->setCellValue('A5', 'Description');
    $sheet->setCellValue('B5', ': ' . $jo['description']);

    $row = 7;
    $headers = ['No', 'Date', 'Trans No', 'Description', 'Cost Amount (IDR)'];
    $col = 'A';
    foreach($headers as $h) {
        $sheet->setCellValue($col.$row, $h);
        $sheet->getStyle($col.$row)->getFont()->setBold(true);
        $sheet->getStyle($col.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
        $sheet->getStyle($col.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($col.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $col++;
    }

    $row++;
    $no = 1;

    foreach($expenses as $data) {
        $sheet->setCellValue('A'.$row, $no++);
        $sheet->setCellValue('B'.$row, date('d/m/Y', strtotime($data['trans_date'])));
        $sheet->setCellValue('C'.$row, $data['transaction_number']);
        $sheet->setCellValue('D'.$row, $data['notes']);
        $sheet->setCellValue('E'.$row, $data['cost']);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $sheet->getStyle("A$row:E$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }

    $sheet->setCellValue('D'.$row, 'TOTAL COST');
    $sheet->setCellValue('E'.$row, $totalCost);
    $sheet->getStyle("D$row:E$row")->getFont()->setBold(true);
    $sheet->getStyle("E$row")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("D$row:E$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;

    $sheet->setCellValue('D'.$row, 'TOTAL BILL (TAGIHAN)');
    $sheet->setCellValue('E'.$row, $totalBill);
    $sheet->getStyle("D$row:E$row")->getFont()->setBold(true);
    $sheet->getStyle("E$row")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("D$row:E$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;

    $sheet->setCellValue('D'.$row, 'GROSS PROFIT');
    $sheet->setCellValue('E'.$row, $grossProfit);
    $sheet->getStyle("D$row:E$row")->getFont()->setBold(true);
    $sheet->getStyle("D$row:E$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EDF7');
    $sheet->getStyle("E$row")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("D$row:E$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    foreach(range('A','E') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="JO_Profitability_'.$joNumber.'.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (\Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}