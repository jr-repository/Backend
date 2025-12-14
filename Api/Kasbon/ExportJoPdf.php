<?php
require_once __DIR__ . '/../../autoload.php';

// --- Composer Autoloader Check (Wajib untuk Mpdf) ---
$vendorPath1 = __DIR__ . '/../../vendor/autoload.php';
$vendorPath2 = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($vendorPath1)) {
    require_once $vendorPath1;
} elseif (file_exists($vendorPath2)) {
    require_once $vendorPath2;
} else {
    // Biarkan script tetap berjalan jika Autoload missing, tapi akan error di class Mpdf
}
// ----------------------------------------------------

use Mpdf\Mpdf;
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
    
    $rows = "";

    foreach ($expenses as $row) {
        $date = date('d/m/Y', strtotime($row['trans_date']));
        $desc = $row['notes'] ?: '-';
        $cost = number_format($row['cost'], 2);
        
        $rows .= "<tr>
            <td style='text-align:center;'>{$date}</td>
            <td>{$row['transaction_number']}</td>
            <td>{$desc}</td>
            <td style='text-align:right;'>{$cost}</td>
        </tr>";
    }

    $fmtBill = number_format($totalBill, 2);
    $fmtCost = number_format($totalCost, 2);
    $fmtGP = number_format($grossProfit, 2);

    $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 10, 'margin_right' => 10]);

    $html = "
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { padding: 4px; vertical-align: top; }
        .box { border: 1px solid #000; padding: 10px; }
        .title { font-size: 14pt; font-weight: bold; text-decoration: underline; margin-bottom: 5px; }
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .main-table th { border: 1px solid #000; padding: 5px; background-color: #f0f0f0; text-align: center; font-weight: bold; }
        .main-table td { border: 1px solid #000; padding: 5px; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .summary-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .summary-table td { padding: 3px; }
    </style>

    <div class='title'>PERHITUNGAN CASH BOND & TAGIHAN</div>
    
    <table class='header-table'>
        <tr>
            <td width='55%' class='box'>
                <table>
                    <tr><td width='100'>NO. JOB ORDER</td><td>: <b>{$jo['transaction_number']}</b></td></tr>
                    <tr><td>TANGGAL</td><td>: " . date('d/m/Y', strtotime($jo['trans_date'])) . "</td></tr>
                    <tr><td>CUSTOMER</td><td>: {$jo['customer_name']}</td></tr>
                    <tr><td>PIC</td><td>: {$jo['pic']}</td></tr>
                    <tr><td>KETERANGAN</td><td>: {$jo['description']}</td></tr>
                </table>
            </td>
            <td width='2%'></td>
            <td width='43%' class='box'>
                <table class='summary-table'>
                    <tr>
                        <td>NILAI TAGIHAN (BILL)</td>
                        <td class='text-right'>Rp {$fmtBill}</td>
                    </tr>
                    <tr>
                        <td>TOTAL BIAYA (COST)</td>
                        <td class='text-right'>Rp {$fmtCost}</td>
                    </tr>
                    <tr><td colspan='2'><hr></td></tr>
                    <tr>
                        <td class='text-bold'>LABA KOTOR (GP)</td>
                        <td class='text-right text-bold'>Rp {$fmtGP}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style='font-weight:bold; margin-bottom:5px;'>RINCIAN PENGELUARAN (EXPENSE HISTORY)</div>
    <table class='main-table'>
        <thead>
            <tr>
                <th width='15%'>TANGGAL</th>
                <th width='20%'>NO. BUKTI</th>
                <th width='45%'>KETERANGAN / NOTES</th>
                <th width='20%'>JUMLAH (IDR)</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
        <tfoot>
            <tr>
                <td colspan='3' class='text-right text-bold'>TOTAL PENGELUARAN</td>
                <td class='text-right text-bold'>{$fmtCost}</td>
            </tr>
        </tfoot>
    </table>
    
    <div style='margin-top: 30px;'>
        <table width='100%'>
            <tr>
                <td align='center' width='33%'>Dibuat Oleh,<br><br><br><br>( Admin )</td>
                <td align='center' width='33%'>Mengetahui,<br><br><br><br>( Manager )</td>
                <td align='center' width='33%'>Disetujui,<br><br><br><br>( Direktur )</td>
            </tr>
        </table>
    </div>
    ";

    $mpdf->WriteHTML($html);
    $mpdf->Output("JO_Profitability_{$joNumber}.pdf", 'I');

} catch (\Throwable $e) {
    http_response_code(500);
    die("Error generating PDF: " . $e->getMessage());
}