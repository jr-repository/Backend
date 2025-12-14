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

use App\Service\AccurateClient;
use Mpdf\Mpdf;

try {
    $accurateClient = new AccurateClient();
    $fromDate = $_GET['fromDate'] ?? date('d/m/Y');
    $toDate = $_GET['toDate'] ?? date('d/m/Y');

    $params = [
        'fromDate' => $fromDate,
        'toDate' => $toDate
    ];

    $responseJson = $accurateClient->call('/glaccount/get-pl-account-amount.do', 'GET', $params);
    $response = json_decode($responseJson, true);

    if (!isset($response['s']) || !$response['s']) {
        throw new \Exception($response['d'][0] ?? 'Gagal mengambil data dari Accurate');
    }

    $data = $response['d'];

    usort($data, function($a, $b) {
        return strcmp($a['accountNo'], $b['accountNo']);
    });

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

    $totalRevenue = 0;
    $totalCOGS = 0;
    $totalExpense = 0;
    $totalOtherIncome = 0;
    $totalOtherExpense = 0;
    
    $totalRevenueReal = 0;
    $totalCOGSReal = 0;
    
    foreach($data as $d) {
        if($d['accountType'] == 'REVENUE' && $d['lvl'] == 1) $totalRevenueReal += $d['amount'];
        if($d['accountType'] == 'COST_OF_GOOD_SOLD' && $d['lvl'] == 1) $totalCOGSReal += $d['amount'];
        if($d['accountType'] == 'EXPENSE' && $d['lvl'] == 1) $totalExpense += $d['amount'];
        if($d['accountType'] == 'OTHER_INCOME' && $d['lvl'] == 1) $totalOtherIncome += $d['amount'];
        if($d['accountType'] == 'OTHER_EXPENSE' && $d['lvl'] == 1) $totalOtherExpense += $d['amount'];
    }

    $grossProfit = $totalRevenueReal - $totalCOGSReal;
    $operatingProfit = $grossProfit - $totalExpense;
    $netProfit = $operatingProfit + $totalOtherIncome - $totalOtherExpense;

    $mpdf = new Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4',
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 15,
        'margin_right' => 15
    ]);

    $html = '
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-name { font-size: 16pt; font-weight: bold; }
        .report-title { font-size: 14pt; margin-top: 5px; }
        .period { font-size: 10pt; margin-top: 5px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 5px; }
        .section-title { font-weight: bold; background-color: #f2f2f2; padding: 5px; margin-top: 10px; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .row-lvl-1 { font-weight: bold; }
        .row-lvl-2 { padding-left: 20px; }
        .row-lvl-3 { padding-left: 40px; color: #333; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; border-top: 1px solid #000; background-color: #fafafa; }
        .double-underline { border-bottom: 3px double #000; }
    </style>

    <div class="header">
        <div class="company-name">MULTI MITRA LOGISTIK</div>
        <div class="report-title">LAPORAN LABA RUGI (PROFIT & LOSS)</div>
        <div class="period">Periode: ' . $fromDate . ' s/d ' . $toDate . '</div>
    </div>';

    $renderSection = function($title, $items) {
        $html = "<tr><td colspan='2' class='section-title'>{$title}</td></tr>";
        if (empty($items)) return $html;
        
        foreach ($items as $item) {
            $class = 'row-lvl-' . min($item['lvl'], 3);
            $name = htmlspecialchars($item['accountName']);
            $no = htmlspecialchars($item['accountNo']);
            $amount = number_format($item['amount'], 2, ',', '.');
            
            $style = "";
            if($item['isParent']) $style = "font-weight:bold;";

            $html .= "
            <tr>
                <td class='{$class}' style='{$style}'>{$no} - {$name}</td>
                <td class='amount' style='{$style}'>{$amount}</td>
            </tr>";
        }
        return $html;
    };

    $html .= '<table>';
    
    $html .= $renderSection('PENDAPATAN USAHA (REVENUE)', $grouped['REVENUE']);
    $html .= "<tr><td style='font-weight:bold; padding-top:10px;'>TOTAL PENDAPATAN</td><td class='amount total-row'>" . number_format($totalRevenueReal, 2, ',', '.') . "</td></tr>";

    $html .= $renderSection('BEBAN POKOK PENJUALAN (COGS)', $grouped['COST_OF_GOOD_SOLD']);
    $html .= "<tr><td style='font-weight:bold; padding-top:10px;'>TOTAL HPP</td><td class='amount total-row'>" . number_format($totalCOGSReal, 2, ',', '.') . "</td></tr>";

    $html .= "<tr><td colspan='2' height='10'></td></tr>";
    $html .= "<tr><td style='font-weight:bold; font-size:11pt; background-color:#e3f2fd; padding:8px;'>LABA KOTOR (GROSS PROFIT)</td><td class='amount total-row' style='font-size:11pt; background-color:#e3f2fd; padding:8px;'>" . number_format($grossProfit, 2, ',', '.') . "</td></tr>";
    
    $html .= $renderSection('BEBAN OPERASIONAL (EXPENSES)', $grouped['EXPENSE']);
    $html .= "<tr><td style='font-weight:bold; padding-top:10px;'>TOTAL BEBAN OPERASIONAL</td><td class='amount total-row'>" . number_format($totalExpense, 2, ',', '.') . "</td></tr>";

    $html .= "<tr><td colspan='2' height='10'></td></tr>";
    $html .= "<tr><td style='font-weight:bold; font-size:11pt; background-color:#fff3e0; padding:8px;'>LABA OPERASIONAL (OPERATING PROFIT)</td><td class='amount total-row' style='font-size:11pt; background-color:#fff3e0; padding:8px;'>" . number_format($operatingProfit, 2, ',', '.') . "</td></tr>";

    $html .= $renderSection('PENDAPATAN LAINNYA', $grouped['OTHER_INCOME']);
    $html .= $renderSection('BEBAN LAINNYA', $grouped['OTHER_EXPENSE']);
    
    $netOther = $totalOtherIncome - $totalOtherExpense;
    $html .= "<tr><td style='font-weight:bold; padding-top:10px;'>TOTAL PENDAPATAN/(BEBAN) LAIN</td><td class='amount total-row'>" . number_format($netOther, 2, ',', '.') . "</td></tr>";

    $html .= "<tr><td colspan='2' height='15'></td></tr>";
    $html .= "<tr>
                <td style='font-weight:bold; font-size:12pt; background-color:#e8f5e9; padding:10px; border-top:2px solid #000; border-bottom:2px solid #000;'>LABA BERSIH (NET PROFIT)</td>
                <td class='amount' style='font-weight:bold; font-size:12pt; background-color:#e8f5e9; padding:10px; border-top:2px solid #000; border-bottom:2px solid #000;'>" . number_format($netProfit, 2, ',', '.') . "</td>
              </tr>";

    $html .= '</table>';
    
    $html .= '
    <div style="margin-top: 40px; width: 100%;">
        <table style="width: 100%; border: none;">
            <tr>
                <td align="center" width="33%">Disiapkan Oleh,<br><br><br><br>____________________<br>( Accounting )</td>
                <td align="center" width="33%">Diperiksa Oleh,<br><br><br><br>____________________<br>( Finance Manager )</td>
                <td align="center" width="33%">Disetujui Oleh,<br><br><br><br>____________________<br>( Direktur )</td>
            </tr>
        </table>
    </div>
    ';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Laporan_Laba_Rugi.pdf', 'I');

} catch (\Throwable $e) {
    echo "Terjadi Kesalahan: " . $e->getMessage();
}