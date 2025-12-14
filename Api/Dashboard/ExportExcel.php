<?php
require_once __DIR__ . '/../../autoload.php';

require_once __DIR__ . '/../../Helpers/accurate_api_helper.php';

$vendorPath1 = __DIR__ . '/../../vendor/autoload.php';
$vendorPath2 = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($vendorPath1)) {
    require_once $vendorPath1;
} elseif (file_exists($vendorPath2)) {
    require_once $vendorPath2;
} else {
    die("Error: Library Vendor tidak ditemukan.");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $toDate = $_GET['toDate'] ?? date('d/m/Y');

    // --- FETCH DATA ---
    // 1. CASH
    $paramsCash = ['fields'=>'no,name,balance', 'filter.accountType.op'=>'EQUAL', 'filter.accountType.val[0]'=>'CASH_BANK', 'asOfDate'=>$toDate, 'sp.sort'=>'no|asc'];
    $resCash = callAccurateApi('/glaccount/list.do', 'GET', $paramsCash);
    $jsonCash = json_decode($resCash, true);
    $cashItems = $jsonCash['d'] ?? [];

    // 2. AR
    $paramsAR = ['fields'=>'no,name,balance', 'filter.accountType.op'=>'EQUAL', 'filter.accountType.val[0]'=>'ACCOUNT_RECEIVABLE', 'asOfDate'=>$toDate, 'sp.sort'=>'no|asc'];
    $resAR = callAccurateApi('/glaccount/list.do', 'GET', $paramsAR);
    $jsonAR = json_decode($resAR, true);
    $arItems = $jsonAR['d'] ?? [];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Neraca Ringkas');

    // --- HEADER ---
    $sheet->setCellValue('A1', 'MULTI MITRA LOGISTIK');
    $sheet->setCellValue('A2', 'LAPORAN POSISI KEUANGAN (RINGKASAN ASET LANCAR)');
    $sheet->setCellValue('A3', 'Per Tanggal: ' . $toDate);
    
    $sheet->mergeCells('A1:C1');
    $sheet->mergeCells('A2:C2');
    $sheet->mergeCells('A3:C3');
    
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- TABLE HEADERS ---
    $row = 5;
    $sheet->setCellValue('A'.$row, 'NO. AKUN');
    $sheet->setCellValue('B'.$row, 'NAMA AKUN');
    $sheet->setCellValue('C'.$row, 'SALDO (IDR)');
    
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle("A$row:C$row")->applyFromArray($headerStyle);
    $row++;

    // --- SECTION 1: KAS & BANK ---
    $sheet->setCellValue('A'.$row, 'I. KAS DAN SETARA KAS');
    $sheet->mergeCells("A$row:C$row");
    $sheet->getStyle("A$row")->getFont()->setBold(true);
    $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    $row++;

    $totalCash = 0;
    foreach($cashItems as $item) {
        $sheet->setCellValue('A'.$row, $item['no']);
        $sheet->setCellValue('B'.$row, $item['name']);
        $sheet->setCellValue('C'.$row, $item['balance']);
        $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A$row:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $totalCash += floatval($item['balance']);
        $row++;
    }
    
    $sheet->setCellValue('B'.$row, 'Total Kas & Bank');
    $sheet->setCellValue('C'.$row, $totalCash);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;

    // --- SECTION 2: PIUTANG ---
    $sheet->setCellValue('A'.$row, 'II. PIUTANG USAHA');
    $sheet->mergeCells("A$row:C$row");
    $sheet->getStyle("A$row")->getFont()->setBold(true);
    $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    $row++;

    $totalAR = 0;
    foreach($arItems as $item) {
        $sheet->setCellValue('A'.$row, $item['no']);
        $sheet->setCellValue('B'.$row, $item['name']);
        $sheet->setCellValue('C'.$row, $item['balance']);
        $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A$row:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $totalAR += floatval($item['balance']);
        $row++;
    }

    $sheet->setCellValue('B'.$row, 'Total Piutang Usaha');
    $sheet->setCellValue('C'.$row, $totalAR);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;

    // --- GRAND TOTAL ---
    $grandTotal = $totalCash + $totalAR;
    $sheet->setCellValue('B'.$row, 'TOTAL ASET LANCAR');
    $sheet->setCellValue('C'.$row, $grandTotal);
    
    $finalStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3E0']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE], 'bottom' => ['borderStyle' => Border::BORDER_DOUBLE]]
    ];
    $sheet->getStyle("A$row:C$row")->applyFromArray($finalStyle);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');

    // Auto Size
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(20);

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Neraca_Ringkas.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}