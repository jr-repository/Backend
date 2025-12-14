<?php
namespace App\Service;

use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use App\Service\AccurateClient;
use App\Utils\ActivityLogger;
use App\Core\Database;

class InvoiceService
{
    private $repo;
    private $userRepo;
    private $accurate;

    public function __construct()
    {
        $this->repo = new InvoiceRepository();
        $this->userRepo = new UserRepository();
        $this->accurate = new AccurateClient();
    }

    public function getList($keyword)
    {
        $invoices = $this->repo->findAll($keyword);
        $data = [];
        foreach ($invoices as $row) {
            $dueDateOutput = '-';
            $agingDays = 0;
            if (!empty($row['due_date']) && $row['due_date'] !== '0000-00-00') {
                $dueDateOutput = date('Y-m-d', strtotime($row['due_date']));
                $agingDays = (int)$row['aging_days'];
            }
            $data[] = [
                'id' => $row['id'],
                'number' => $row['transaction_number'],
                'transDate' => date('d/m/Y', strtotime($row['trans_date'])),
                'dueDate' => $dueDateOutput,
                'customerName' => $row['customer_name'],
                'totalAmount' => floatval($row['total_amount']),
                'downPayment' => floatval($row['down_payment']),
                'netBalance' => floatval($row['net_balance']),
                'status' => $row['status'],
                'agingDays' => $agingDays
            ];
        }
        return $data;
    }

    public function getDetail($id)
    {
        $header = $this->repo->findById($id);
        if (!$header) return null;

        $details = $this->repo->findDetailsByInvoiceId($id);
        $items = [];
        foreach ($details as $row) {
            $items[] = [
                'itemNo' => $row['item_no'],
                'itemName' => $row['item_name'],
                'quantity' => floatval($row['quantity']),
                'unitPrice' => floatval($row['unit_price']),
                'itemDiscPercent' => $row['item_disc_percent'],
                'useTax1' => $row['ppn_rate'] > 0,
                'useTax3' => $row['pph_rate'] > 0,
                'ppnRate' => floatval($row['ppn_rate']),
                'pphRate' => floatval($row['pph_rate']),
                'lineTotal' => floatval($row['line_total'])
            ];
        }

        $fileList = [];
        if ($header['status'] === 'PAID') {
            $files = $this->repo->getPaymentFiles($id);
            foreach ($files as $f) {
                $fileList[] = [
                    'name' => $f['file_name'],
                    'url' => "https://kasbon2.multimitralogistik.id/" . $f['file_path']
                ];
            }
        }

        $dueDateOutput = (!empty($header['due_date']) && $header['due_date'] !== '0000-00-00') ? $header['due_date'] : '-';

        return [
            'id' => $header['id'],
            'number' => $header['transaction_number'],
            'transDate' => $header['trans_date'],
            'dueDate' => $dueDateOutput,
            'status' => $header['status'], 
            'customer' => [
                'customerNo' => $header['customer_no'],
                'name' => $header['customer_name']
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
            'items' => $items,
            'paymentFiles' => $fileList
        ];
    }

    public function saveTransaction($data, $userId, $userName)
    {
        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();

        try {
            $id = $data['id'] ?? 0;
            $trxNo = ($id > 0) ? $this->repo->getTransactionNumberById($id) : ($data['number'] ?? 'INV-' . date('Ymd-His'));
            
            if ($id > 0) {
                $current = $this->repo->findById($id);
                if (in_array($current['status'], ['SUBMITTED', 'APPROVED', 'PAID'])) {
                    throw new \Exception("Data sudah di-approve/paid, tidak bisa diedit.");
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
            $grandTotal = $afterGlobalDisc + $totalPPN - $totalPPh;
            
            $downPayment = floatval($data['downPayment'] ?? 0);
            $netBalance = $grandTotal - $downPayment;
            
            $saveData = [
                'trxNo' => $trxNo,
                'transDate' => date('Y-m-d', strtotime($data['transDate'])),
                'dueDate' => (!empty($data['dueDate']) && $data['dueDate'] !== '0000-00-00') ? date('Y-m-d', strtotime($data['dueDate'])) : null,
                'custNo' => $data['customerNo'],
                'custName' => $data['customerName'] ?? '',
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
                $invoiceId = $id;
                $action = 'UPDATE';
            } else {
                $invoiceId = $this->repo->create($saveData);
                $action = 'CREATE';
            }

            foreach ($detailItems as $d) {
                $this->repo->addDetail($invoiceId, $d);
            }

            $db->commit();
            ActivityLogger::log($userId, $userName, 'SALES_INVOICE', $action, $trxNo, $grandTotal);

            return ['success' => true, 'message' => 'Invoice Disimpan (Menunggu Approval)', 'number' => $trxNo];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function approve($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        $roleCheck = $this->userRepo->findByUsername($userId); 
        $userRole = 'user'; 
        
        $db = Database::getInstance()->getConnection();
        $res = $db->query("SELECT role FROM users WHERE id = $userId")->fetch_assoc();
        if ($res) $userRole = $res['role'];

        if (!in_array('INVOICE', $perms) && $userRole !== 'admin') {
            return ['success' => false, 'message' => 'AKSES DITOLAK: Anda tidak memiliki izin Approval Invoice.'];
        }

        $header = $this->repo->findById($id);
        if (!$header) return ['success' => false, 'message' => 'Invoice tidak ditemukan.'];
        if (in_array($header['status'], ['SUBMITTED', 'APPROVED'])) {
            return ['success' => false, 'message' => 'Invoice sudah di-approve sebelumnya.'];
        }

        $details = $this->repo->findDetailsByInvoiceId($id);
        if (empty($details)) return ['success' => false, 'message' => 'Detail item kosong.'];

        $payload = [
            'transDate' => date('d/m/Y', strtotime($header['trans_date'])),
            'customerNo' => $header['customer_no'],
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
            $payload["{$key}.useTax1"] = ($row['ppn_rate'] > 0) ? true : false;
            $payload["{$key}.useTax3"] = ($row['pph_rate'] > 0) ? true : false;
            $i++;
        }

        $resAccurate = json_decode($this->accurate->call('/sales-invoice/save.do', 'POST', $payload), true);

        if (isset($resAccurate['s']) && $resAccurate['s'] === true) {
            $this->repo->updateStatus($id, 'SUBMITTED', $resAccurate['r']['id'], $resAccurate['r']['number']);
            return ['success' => true, 'message' => 'Approved & Terkirim ke Accurate. No: ' . $resAccurate['r']['number']];
        } else {
            $err = isset($resAccurate['d']) ? (is_array($resAccurate['d']) ? implode(', ', $resAccurate['d']) : $resAccurate['d']) : 'Unknown Error';
            return ['success' => false, 'message' => 'Gagal Accurate: ' . $err];
        }
    }

    public function reject($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        if (!in_array('INVOICE', $perms)) {
            return ['success' => false, 'message' => 'AKSES DITOLAK: Tidak ada izin Approval Invoice.'];
        }

        if ($this->repo->updateStatus($id, 'REJECTED')) {
            return ['success' => true, 'message' => 'Invoice Ditolak (Rejected)'];
        }
        return ['success' => false, 'message' => 'Gagal reject database.'];
    }

    public function processPayment($id, $paidAmount, $userId, $files)
    {
        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();

        try {
            $invoice = $this->repo->findById($id);
            if (!$invoice || $invoice['status'] === 'PAID') {
                throw new \Exception("Status invoice sudah PAID atau tidak ditemukan.");
            }

            if ($paidAmount <= 0) throw new \Exception("Jumlah pembayaran harus lebih dari 0.");

            $newDownPayment = floatval($invoice['down_payment']) + $paidAmount;
            $newNetBalance = floatval($invoice['total_amount']) - $newDownPayment;
            
            $newStatus = 'SUBMITTED';
            if ($newNetBalance <= 0.01) {
                $newStatus = 'PAID';
                $newNetBalance = 0;
            }

            $this->repo->updatePaymentStatus($id, $newStatus, $newDownPayment, $newNetBalance);

            if (!empty($files['name'][0])) {
                $targetDir = __DIR__ . '/../../uploads/invoice/';
                if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

                $count = count($files['name']);
                for ($i = 0; $i < $count; $i++) {
                    $fileName = basename($files['name'][$i]);
                    $tmpName = $files['tmp_name'][$i];
                    $newFileName = time() . '_' . rand(100, 999) . '_' . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                        $this->repo->addPaymentFile($id, $fileName, 'uploads/invoice/' . $newFileName, $userId);
                    }
                }
            }

            $db->commit();
            return ['success' => true, 'message' => 'Pembayaran berhasil dicatat.'];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getMasterCustomers()
    {
        $res = $this->accurate->call('/customer/list.do', 'GET', ['fields' => 'id,name,customerNo', 'pageSize' => 100]);
        $json = json_decode($res, true);
        return (isset($json['s']) && $json['s']) ? $json : null;
    }

    public function getMasterItems()
    {
        $res = $this->accurate->call('/item/list.do', 'GET', ['fields' => 'id,name,no,unitName,itemType', 'pageSize' => 100]);
        $json = json_decode($res, true);
        return (isset($json['s']) && $json['s']) ? $json : null;
    }
}