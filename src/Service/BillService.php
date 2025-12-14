<?php
namespace App\Service;

use App\Repository\BillRepository;
use App\Repository\UserRepository;
use App\Service\AccurateClient;
use App\Utils\ActivityLogger;
use App\Core\Database;

class BillService
{
    private $repo;
    private $userRepo;
    private $accurate;

    public function __construct()
    {
        $this->repo = new BillRepository();
        $this->userRepo = new UserRepository();
        $this->accurate = new AccurateClient();
    }

    public function getList($keyword)
    {
        $bills = $this->repo->findAll($keyword);
        $data = [];
        foreach ($bills as $row) {
            $data[] = [
                'id' => $row['id'],
                'number' => $row['transaction_number'],
                'transDate' => date('d/m/Y', strtotime($row['trans_date'])),
                'vendorName' => $row['vendor_name'],
                'totalAmount' => floatval($row['total_amount']),
                'netBalance' => floatval($row['net_balance']),
                'status' => $row['status']
            ];
        }
        return $data;
    }

    public function getDetail($id)
    {
        $header = $this->repo->findById($id);
        if (!$header) return null;

        $details = $this->repo->findDetailsByBillId($id);
        $items = [];
        foreach ($details as $row) {
            $items[] = [
                'itemNo' => $row['item_no'],
                'itemName' => $row['item_name'],
                'quantity' => floatval($row['quantity']),
                'unitPrice' => floatval($row['unit_price']),
                'itemDiscPercent' => $row['item_disc_percent'],
                'ppnRate' => floatval($row['ppn_rate']),
                'pphRate' => floatval($row['pph_rate']),
                'lineTotal' => floatval($row['line_total'])
            ];
        }

        return [
            'id' => $header['id'],
            'number' => $header['transaction_number'],
            'transDate' => $header['trans_date'],
            'status' => $header['status'],
            'vendor' => [
                'vendorNo' => $header['vendor_no'],
                'name' => $header['vendor_name']
            ],
            'description' => $header['description'],
            'globalDiscPercent' => floatval($header['global_disc_percent']),
            'downPayment' => floatval($header['down_payment']),
            'summary' => [
                'subtotal' => floatval($header['subtotal']),
                'ppn' => floatval($header['tax_ppn_amount']),
                'pph' => floatval($header['tax_pph_amount']),
                'total' => floatval($header['total_amount']),
                'net' => floatval($header['net_balance'])
            ],
            'items' => $items
        ];
    }

    public function saveTransaction($data, $userId, $userName)
    {
        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();

        try {
            $id = $data['id'] ?? 0;
            $trxNo = ($id > 0) ? $this->repo->getTransactionNumberById($id) : ($data['number'] ?? 'BILL-' . date('Ymd-His'));

            if ($id > 0) {
                $check = $this->repo->findById($id);
                if (in_array($check['status'], ['SUBMITTED', 'APPROVED'])) {
                    throw new \Exception("Data status " . $check['status'] . " tidak bisa diedit.");
                }
            }

            $subtotal = 0; $totalPPN = 0; $totalPPh = 0;
            $detailItems = [];

            foreach ($data['items'] as $item) {
                $qty = floatval($item['quantity']);
                $price = floatval($item['unitPrice']);
                $itemDiscStr = $item['itemDiscPercent'] ?? '0';
                $itemDiscVal = floatval($itemDiscStr); 
                
                $gross = $qty * $price;
                $discAmount = $gross * ($itemDiscVal / 100);
                $netItem = $gross - $discAmount;
                
                $ppnRate = floatval($item['ppnRate'] ?? 0);
                $pphRate = floatval($item['pphRate'] ?? 0);

                $totalPPN += $netItem * ($ppnRate / 100);
                $totalPPh += $netItem * ($pphRate / 100);
                $subtotal += $netItem;
                
                $detailItems[] = [
                    'item_no' => $item['itemNo'],
                    'item_name' => $item['itemName'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'item_disc_percent' => $itemDiscStr,
                    'item_disc_amount' => $discAmount,
                    'ppn_rate' => $ppnRate,
                    'pph_rate' => $pphRate,
                    'line_total' => $netItem
                ];
            }

            $globalDiscPercent = floatval($data['globalDiscPercent'] ?? 0);
            $globalDiscAmount = $subtotal * ($globalDiscPercent / 100);
            $afterGlobalDisc = $subtotal - $globalDiscAmount;
            
            $totalPPN = $totalPPN * (1 - ($globalDiscPercent/100));
            $totalPPh = $totalPPh * (1 - ($globalDiscPercent/100));
            $grandTotal = $afterGlobalDisc + $totalPPN; 
            $downPayment = floatval($data['downPayment'] ?? 0);
            $netBalance = $grandTotal - $downPayment;

            $saveData = [
                'trxNo' => $trxNo,
                'transDate' => date('Y-m-d', strtotime($data['transDate'])),
                'vendNo' => $data['vendorNo'],
                'vendName' => $data['vendorName'] ?? '',
                'desc' => $data['description'] ?? '',
                'subtotal' => $subtotal,
                'globalDiscPercent' => $globalDiscPercent,
                'globalDiscAmount' => $globalDiscAmount,
                'totalPPN' => $totalPPN,
                'totalPPh' => $totalPPh,
                'grandTotal' => $grandTotal,
                'downPayment' => $downPayment,
                'netBalance' => $netBalance
            ];

            if ($id > 0) {
                $this->repo->update($id, $saveData);
                $this->repo->deleteDetails($id);
                $billId = $id;
                $action = 'UPDATE';
            } else {
                $billId = $this->repo->create($saveData);
                $action = 'CREATE';
            }

            foreach ($detailItems as $d) {
                $this->repo->addDetail($billId, $d);
            }

            $db->commit();
            ActivityLogger::log($userId, $userName, 'VENDOR_BILL', $action, $trxNo, $grandTotal);

            return ['success' => true, 'message' => 'Tagihan Disimpan (Menunggu Approval)', 'number' => $trxNo];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function approve($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        $db = Database::getInstance()->getConnection();
        $res = $db->query("SELECT role FROM users WHERE id = $userId")->fetch_assoc();
        $userRole = $res ? $res['role'] : 'user';

        if (!in_array('BILL', $perms) && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'AKSES DITOLAK: Anda tidak memiliki izin Approval Bill.'];
        }

        $header = $this->repo->findById($id);
        if (!$header) return ['success' => false, 'message' => 'Tagihan tidak ditemukan.'];
        if (in_array($header['status'], ['SUBMITTED', 'APPROVED'])) {
            return ['success' => false, 'message' => 'Tagihan sudah di-approve sebelumnya.'];
        }

        $details = $this->repo->findDetailsByBillId($id);
        if (empty($details)) return ['success' => false, 'message' => 'Detail item kosong.'];

        $payload = [
            'transDate' => date('d/m/Y', strtotime($header['trans_date'])),
            'vendorNo' => $header['vendor_no'],
            'billNumber' => $header['transaction_number'], 
            'description' => $header['description'],
            'branchId' => $this->accurate->getConfig('branch_id'),
            'cashDiscPercent' => floatval($header['global_disc_percent']),
        ];

        $i = 0;
        foreach ($details as $row) {
            $key = "detailItem[{$i}]";
            $payload["{$key}.itemNo"] = $row['item_no'];
            $payload["{$key}.quantity"] = floatval($row['quantity']);
            $payload["{$key}.unitPrice"] = floatval($row['unit_price']);
            $payload["{$key}.itemDiscPercent"] = $row['item_disc_percent'] ?? 0;
            $payload["{$key}.warehouseName"] = $this->accurate->getConfig('warehouse');
            $payload["{$key}.useTax1"] = ($row['ppn_rate'] > 0) ? 'true' : 'false';
            $payload["{$key}.useTax3"] = ($row['pph_rate'] > 0) ? 'true' : 'false';
            $i++;
        }

        $resAccurate = json_decode($this->accurate->call('/purchase-invoice/save.do', 'POST', $payload), true);

        if (isset($resAccurate['s']) && $resAccurate['s'] === true) {
            $this->repo->updateStatus($id, 'SUBMITTED', $resAccurate['r']['id']);
            return ['success' => true, 'message' => 'Approved & Terkirim ke Accurate. No: ' . $resAccurate['r']['number']];
        } else {
            $err = isset($resAccurate['d']) ? (is_array($resAccurate['d']) ? implode(', ', $resAccurate['d']) : $resAccurate['d']) : 'Unknown Error';
            return ['success' => false, 'message' => 'Gagal Accurate: ' . $err];
        }
    }

    public function reject($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        $db = Database::getInstance()->getConnection();
        $res = $db->query("SELECT role FROM users WHERE id = $userId")->fetch_assoc();
        $userRole = $res ? $res['role'] : 'user';

        if (!in_array('BILL', $perms) && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'AKSES DITOLAK: Tidak ada izin Approval Bill.'];
        }

        if ($this->repo->updateStatus($id, 'REJECTED')) {
            return ['success' => true, 'message' => 'Tagihan Ditolak (Rejected)'];
        }
        return ['success' => false, 'message' => 'Gagal reject.'];
    }

    public function getMasterVendors()
    {
        $res = $this->accurate->call('/vendor/list.do', 'GET', ['fields' => 'vendorNo,name']);
        $json = json_decode($res, true);
        return (isset($json['s']) && $json['s']) ? $json : null;
    }
}