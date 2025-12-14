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

use Mpdf\Mpdf;

try {
    $toDate = $_GET['toDate'] ?? date('d/m/Y');
    
    // --- FETCH DATA ---\
    // 1. CASH & BANK
    $paramsCash = [
        'fields' => 'no,name,balance',
        'filter.accountType.op' => 'EQUAL',
        'filter.accountType.val[0]' => 'CASH_BANK',
        'asOfDate' => $toDate,
        'sp.sort' => 'no|asc'
    ];
    $resCash = callAccurateApi('/glaccount/list.do', 'GET', $paramsCash);
    $jsonCash = json_decode($resCash, true);
    $cashItems = $jsonCash['d'] ?? [];
    $totalCash = 0;
    foreach($cashItems as $c) $totalCash += floatval($c['balance']);

    // 2. ACCOUNTS RECEIVABLE
    $paramsAR = [
        'fields' => 'no,name,balance',
        'filter.accountType.op' => 'EQUAL',
        'filter.accountType.val[0]' => 'ACCOUNT_RECEIVABLE',
        'asOfDate' => $toDate,
        'sp.sort' => 'no|asc'
    ];
    $resAR = callAccurateApi('/glaccount/list.do', 'GET', $paramsAR);
    $jsonAR = json_decode($resAR, true);
    $arItems = $jsonAR['d'] ?? [];
    $totalAR = 0;
    foreach($arItems as $a) $totalAR += floatval($a['balance']);

    $totalCurrentAssets = $totalCash + $totalAR;

    // --- GENERATE PDF ---
    $mpdf = new Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4',
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_left' => 15,
        'margin_right' => 15
    ]);

    $html = '
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
        .report-title { font-size: 14pt; font-weight: bold; color: #1a237e; }
        .period { font-size: 10pt; color: #555; margin-top: 5px; }
        
        .section-header { 
            background-color: #f5f5f5; 
            font-weight: bold; 
            padding: 8px; 
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            margin-top: 15px;
            color: #0d47a1;
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { padding: 6px 8px; }
        .row-item td { border-bottom: 1px dashed #eee; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .subtotal-row td { 
            border-top: 1px solid #999; 
            font-weight: bold; 
            background-color: #fafafa;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        
        .grand-total {
            background-color: #e3f2fd;
            border-top: 2px solid #1565C0;
            border-bottom: 2px solid #1565C0;
            font-size: 11pt;
            font-weight: bold;
            color: #0D47A1;
        }
        .account-no { color: #666; font-family: monospace; font-size: 9pt; }
    </style>

    <div class="header">
        <div class="company-name">MULTI MITRA LOGISTIK</div>
        <div class="report-title">LAPORAN POSISI KEUANGAN (RINGKASAN)</div>
        <div class="period">Per Tanggal: ' . $toDate . '</div>
    </div>

    <div class="section-header">ASET LANCAR (CURRENT ASSETS)</div>
    
    <div style="font-weight:bold; margin-top:10px; margin-bottom:5px; padding-left:5px;">1. KAS DAN SETARA KAS (CASH & BANK)</div>
    <table>';
    
    foreach ($cashItems as $item) {
        $html .= '
        <tr class="row-item">
            <td width="15%" class="account-no">'.$item['no'].'</td>
            <td width="55%">'.$item['name'].'</td>
            <td width="30%" class="text-right">Rp '.number_format($item['balance'], 2, ',', '.').'</td>
        </tr>';
    }

    $html .= '
        <tr class="subtotal-row">
            <td colspan="2" class="text-right">Total Kas & Bank</td>
            <td class="text-right">Rp '.number_format($totalCash, 2, ',', '.').'</td>
        </tr>
    </table>

    <div style="font-weight:bold; margin-top:20px; margin-bottom:5px; padding-left:5px;">2. PIUTANG USAHA (ACCOUNTS RECEIVABLE)</div>
    <table>';

    if (empty($arItems)) {
        $html .= '<tr><td colspan="3" style="text-align:center; font-style:italic; color:#999;">Tidak ada data piutang</td></tr>';
    } else {
        foreach ($arItems as $item) {
            $html .= '
            <tr class="row-item">
                <td width="15%" class="account-no">'.$item['no'].'</td>
                <td width="55%">'.$item['name'].'</td>
                <td width="30%" class="text-right">Rp '.number_format($item['balance'], 2, ',', '.').'</td>
            </tr>';
        }
    }

    $html .= '
        <tr class="subtotal-row">
            <td colspan="2" class="text-right">Total Piutang Usaha</td>
            <td class="text-right">Rp '.number_format($totalAR, 2, ',', '.').'</td>
        </tr>
    </table>

    <br><br>
    <table>
        <tr class="grand-total">
            <td width="70%" class="text-right">TOTAL ASET LANCAR (KAS + PIUTANG)</td>
            <td width="30%" class="text-right">Rp '.number_format($totalCurrentAssets, 2, ',', '.').'</td>
        </tr>
    </table>

    <div style="margin-top: 50px; width: 100%;">
        <table style="width: 100%; border: none;">
            <tr>
                <td align="center" width="50%">Dibuat Oleh,<br><br><br><br>____________________<br>( Finance / Accounting )</td>
                <td align="center" width="50%">Disetujui Oleh,<br><br><br><br>____________________<br>( Direktur )</td>
            </tr>
        </table>
    </div>
    ';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Neraca_Ringkas_'.$toDate.'.pdf', 'I');

} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage();
}