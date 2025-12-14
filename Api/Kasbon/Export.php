<?php
// Wajib ada untuk memuat semua class di folder src
require_once __DIR__ . '/../../autoload.php';

use App\Core\Database;
use App\Core\Response; // Tambahkan Response untuk error handling

// Tidak pakai JSON Header karena outputnya File/HTML
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }


$type = $_GET['type'] ?? 'excel'; // excel | pdf
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

try {
    // 1. Ambil koneksi dari Singleton Class yang baru
    $conn = Database::getInstance()->getConnection();

    // Query Data
    $sql = "SELECT 
                t.transaction_number,
                t.trans_date,
                t.description as header_desc,
                t.status,
                d.account_name,
                d.notes,
                d.amount as cost,
                d.bill_amount as bill,
                j.transaction_number as jo_number
            FROM kasbon_details d
            JOIN kasbon_transactions t ON d.kasbon_id = t.id
            LEFT JOIN job_orders j ON d.job_order_id = j.id
            WHERE t.status != 'REJECTED' 
            AND t.trans_date BETWEEN ? AND ?
            ORDER BY t.trans_date ASC, t.id ASC";

    // Gunakan Prepared Statement untuk keamanan dan konsistensi
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Pastikan output file header dikirim sebelum HTML
    if ($type === 'excel') {
        $filename = "Laporan_Kasbon_" . date('Ymd') . ".xls";
        // Header untuk Excel (XLS/HTML Table)
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");
    } else {
        // Mode PDF / Print (HTML Output)
        echo "<html><head><title>Laporan Kasbon</title>";
        echo "<style>
                body { font-family: sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #333; padding: 5px; }
                th { background-color: #eee; }
                .text-right { text-align: right; }
                .header { text-align: center; margin-bottom: 20px; }
              </style></head><body onload='window.print()'>";
        echo "<div class='header'>
                <h2>LAPORAN KASBON & EXPENSE</h2>
                <p>Periode: " . date('d/m/Y', strtotime($startDate)) . " s/d " . date('d/m/Y', strtotime($endDate)) . "</p>
              </div>";
    }
?>

<table border="1">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>No Transaksi</th>
            <th>Keterangan</th>
            <th>Akun Biaya</th>
            <th>Job Order</th>
            <th>Biaya (Cost)</th>
            <th>Tagihan (Bill)</th>
            <th>Gross Profit</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $totalCost = 0;
        $totalBill = 0;
        $totalGP = 0;
        
        while($row = $result->fetch_assoc()): 
            $gp = $row['bill'] - $row['cost'];
            $totalCost += $row['cost'];
            $totalBill += $row['bill'];
            $totalGP += $gp;
        ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($row['trans_date'])) ?></td>
            <td><?= $row['transaction_number'] ?></td>
            <td><?= htmlspecialchars($row['header_desc'] . ' - ' . $row['notes']) ?></td>
            <td><?= htmlspecialchars($row['account_name']) ?></td>
            <td><?= htmlspecialchars($row['jo_number'] ?? '-') ?></td>
            <td align="right"><?= number_format($row['cost'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($row['bill'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($gp, 0, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
    <tfoot>
        <tr style="background-color: #ddd; font-weight: bold;">
            <td colspan="5" align="right">GRAND TOTAL</td>
            <td align="right"><?= number_format($totalCost, 0, ',', '.') ?></td>
            <td align="right"><?= number_format($totalBill, 0, ',', '.') ?></td>
            <td align="right"><?= number_format($totalGP, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>

<?php 
    if($type !== 'excel') {
        echo "</body></html>";
    }
    exit;

} catch (\Throwable $e) {
    // 2. Error Handling: Menampilkan pesan error yang jelas (bukan blank page)
    http_response_code(500); 
    header('Content-Type: text/plain'); 
    echo "Fatal Error Export:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit;
}