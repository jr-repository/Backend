<?php
require_once __DIR__ . '/../../autoload.php';

$vendorPath1 = __DIR__ . '/../../vendor/autoload.php';
$vendorPath2 = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($vendorPath1)) {
    require_once $vendorPath1;
} elseif (file_exists($vendorPath2)) {
    require_once $vendorPath2;
} else {
    die("Error: Library Vendor (Composer) tidak ditemukan.");
}

use App\Core\Response;
use App\Service\AccurateClient;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $accurateClient = new AccurateClient();
    $fromDate = $_GET['fromDate'] ?? date('d/m/Y');
    $toDate = $_GET['toDate'] ?? date('d/m/Y');

    $params = ['fromDate' => $fromDate, 'toDate' => $toDate];
    $responseJson = $accurateClient->call('/glaccount/get-pl-account-amount.do', 'GET', $params);
    $response = json_decode($responseJson, true);

    if (!isset($response['s']) || !$response['s']) {
        throw new \Exception($response['d'][0] ?? 'Gagal mengambil data Accurate');
    }

    $data = $response['d'];
    usort($data, function($a, $b) { return strcmp($a['accountNo'], $b['accountNo']); });

    $grouped = [
        'REVENUE' => [],
        'COST_OF_GOOD_SOLD' => [],
        'EXPENSE' => [],
        'OTHER_INCOME' => [],
        'OTHER_EXPENSE' => []
    ];

    foreach ($data as $item) {
        if (isset($grouped[$item['accountType']])) {
            $grouped[$item['accountType']][] = $item;
        }
    }

    $totalRevenueReal = 0; $totalCOGSReal = 0; $totalExpense = 0; $totalOtherIncome = 0; $totalOtherExpense = 0;
    foreach($data as $d) {
        if($d['lvl'] == 1) {
            if($d['accountType'] == 'REVENUE') $totalRevenueReal += $d['amount'];
            if($d['accountType'] == 'COST_OF_GOOD_SOLD') $totalCOGSReal += $d['amount'];
            if($d['accountType'] == 'EXPENSE') $totalExpense += $d['amount'];
            if($d['accountType'] == 'OTHER_INCOME') $totalOtherIncome += $d['amount'];
            if($d['accountType'] == 'OTHER_EXPENSE') $totalOtherExpense += $d['amount'];
        }
    }
    
    $grossProfit = $totalRevenueReal - $totalCOGSReal;
    $operatingProfit = $grossProfit - $totalExpense;
    $netProfit = $operatingProfit + $totalOtherIncome - $totalOtherExpense;

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laba Rugi');

    $sheet->setCellValue('A1', 'MULTI MITRA LOGISTIK');
    $sheet->setCellValue('A2', 'LAPORAN LABA RUGI (PROFIT & LOSS)');
    $sheet->setCellValue('A3', "Periode: $fromDate s/d $toDate");
    
    $sheet->mergeCells('A1:C1');
    $sheet->mergeCells('A2:C2');
    $sheet->mergeCells('A3:C3');
    
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 5;
    $sheet->setCellValue('A'.$row, 'NO. AKUN');
    $sheet->setCellValue('B'.$row, 'NAMA AKUN');
    $sheet->setCellValue('C'.$row, 'JUMLAH (IDR)');
    
    $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle("A$row:C$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    $sheet->getStyle("A$row:C$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;

    $printSection = function($title, $items) use (&$sheet, &$row) {
        $sheet->setCellValue('A'.$row, $title);
        $sheet->mergeCells("A$row:C$row");
        $sheet->getStyle("A$row")->getFont()->setBold(true);
        $sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF3E0');
        $row++;

        foreach($items as $item) {
            $indent = ($item['lvl'] - 1) * 2; 
            $sheet->setCellValue('A'.$row, $item['accountNo']);
            $sheet->setCellValue('B'.$row, $item['accountName']);
            $sheet->setCellValue('C'.$row, $item['amount']);
            
            $sheet->getStyle('B'.$row)->getAlignment()->setIndent($indent);
            $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            
            if($item['isParent']) {
                $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            }
            $row++;
        }
    };

    $printSection('PENDAPATAN USAHA', $grouped['REVENUE']);
    $sheet->setCellValue('B'.$row, 'TOTAL PENDAPATAN');
    $sheet->setCellValue('C'.$row, $totalRevenueReal);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;

    $printSection('BEBAN POKOK PENJUALAN', $grouped['COST_OF_GOOD_SOLD']);
    $sheet->setCellValue('B'.$row, 'TOTAL HPP');
    $sheet->setCellValue('C'.$row, $totalCOGSReal);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;

    $sheet->setCellValue('B'.$row, 'LABA KOTOR (GROSS PROFIT)');
    $sheet->setCellValue('C'.$row, $grossProfit);
    $sheet->getStyle("A$row:C$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBBDEFB');
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    $printSection('BEBAN OPERASIONAL', $grouped['EXPENSE']);
    $sheet->setCellValue('B'.$row, 'TOTAL BEBAN OPERASIONAL');
    $sheet->setCellValue('C'.$row, $totalExpense);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;

    $sheet->setCellValue('B'.$row, 'LABA OPERASIONAL');
    $sheet->setCellValue('C'.$row, $operatingProfit);
    $sheet->getStyle("A$row:C$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE0B2');
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    $printSection('PENDAPATAN LAINNYA', $grouped['OTHER_INCOME']);
    $printSection('BEBAN LAINNYA', $grouped['OTHER_EXPENSE']);
    $row += 1;

    $sheet->setCellValue('B'.$row, 'LABA BERSIH (NET PROFIT)');
    $sheet->setCellValue('C'.$row, $netProfit);
    $sheet->getStyle("A$row:C$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC8E6C9');
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$row:C$row")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
    $sheet->getStyle("A$row:C$row")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(50);
    $sheet->getColumnDimension('C')->setWidth(20);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Laporan_Laba_Rugi.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}