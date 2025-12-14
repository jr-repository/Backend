<?php
namespace App\Service;

use App\Repository\KasbonRepository;
use App\Repository\UserRepository;
use App\Service\AccurateClient;
use App\Utils\ActivityLogger;
use App\Core\Database;

class KasbonService
{
    private $repo;
    private $userRepo;
    private $accurate;

    public function __construct()
    {
        $this->repo = new KasbonRepository();
        $this->userRepo = new UserRepository();
        $this->accurate = new AccurateClient();
    }

    public function getList($q, $status)
    {
        $data = $this->repo->findAll($q, $status);
        $result = [];
        foreach ($data as $row) {
            $result[] = [
                'id' => $row['id'],
                'number' => $row['transaction_number'],
                'transDate' => date('d/m/Y', strtotime($row['trans_date'])),
                'description' => $row['description'],
                'amount' => floatval($row['total_amount']),
                'status' => $row['status'],
                'bank_name' => $row['bank_name']
            ];
        }
        return $result;
    }

    public function save($data, $userId, $userName)
    {
        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();
        try {
            $id = $data['id'] ?? 0;
            $trxNo = ($id > 0) ? $this->repo->findById($id)['transaction_number'] : 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);
            
            if ($id > 0) {
                $curr = $this->repo->findById($id);
                if ($curr['status'] === 'APPROVED') throw new \Exception("Data sudah di-Approve, tidak bisa diedit.");
                $this->repo->update($id, [
                    'date' => date('Y-m-d', strtotime($data['transDate'])),
                    'bankId' => $data['bankId'],
                    'bankName' => $data['bankName'] ?? '',
                    'desc' => $data['description'] ?? ''
                ]);
                $this->repo->deleteDetails($id);
                $kasbonId = $id;
                $action = 'UPDATE';
            } else {
                $createData = [
                    'trxNo' => $trxNo,
                    'date' => date('Y-m-d', strtotime($data['transDate'])),
                    'bankId' => $data['bankId'],
                    'bankName' => $data['bankName'] ?? '',
                    'desc' => $data['description'] ?? ''
                ];
                $kasbonId = $this->repo->create($createData, $userName);
                $action = 'CREATE';
            }

            $totalAmount = 0;
            foreach ($data['detailAccount'] as $item) {
                $totalAmount += floatval($item['amount']);
                $this->repo->addDetail($kasbonId, [
                    'accountNo' => $item['accountNo'],
                    'accountName' => $item['accountName'] ?? '',
                    'notes' => $item['detailNotes'] ?? '',
                    'amount' => floatval($item['amount']),
                    'billAmount' => floatval($item['billAmount'] ?? 0),
                    'jobOrderId' => !empty($item['jobOrderId']) ? (int)$item['jobOrderId'] : null
                ]);
            }

            $db->commit();
            ActivityLogger::log($userId, $userName, 'KASBON_EXPENSE', $action, $trxNo, $totalAmount);
            return ['success' => true, 'message' => 'Data tersimpan. Menunggu Approval.'];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function approve($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        if (!in_array('KASBON', $perms)) return ['success' => false, 'message' => 'AKSES DITOLAK: Tidak ada izin Approval Kasbon.'];

        $header = $this->repo->findById($id);
        if ($header['status'] === 'APPROVED') return ['success' => false, 'message' => 'Transaksi sudah di-approve.'];

        $details = $this->repo->findDetailsByKasbonId($id);
        
        $payload = [
            'transDate' => date('d/m/Y', strtotime($header['trans_date'])),
            'bankId' => $header['bank_id'],
            'branchId' => $this->accurate->getConfig('branch_id'),
            'description' => $header['description'] . " (Ref: " . $header['transaction_number'] . ")",
        ];

        foreach($details as $idx => $row) {
            $payload["detailAccount[{$idx}].accountNo"] = $row['account_no'];
            $payload["detailAccount[{$idx}].amount"] = floatval($row['amount']);
            $payload["detailAccount[{$idx}].detailNotes"] = $row['notes'];
        }

        $res = json_decode($this->accurate->call('/other-payment/save.do', 'POST', $payload), true);
        if (isset($res['s']) && $res['s']) {
            $this->repo->updateStatus($id, 'APPROVED', $res['r']['id']);
            return ['success' => true, 'message' => 'Approved & Terkirim ke Accurate. No: ' . $res['r']['number']];
        } else {
            return ['success' => false, 'message' => 'Gagal kirim ke Accurate: ' . json_encode($res['d'])];
        }
    }

    public function reject($id, $userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        if (!in_array('KASBON', $perms)) return ['success' => false, 'message' => 'AKSES DITOLAK: Tidak ada izin Reject Kasbon.'];
        
        $curr = $this->repo->findById($id);
        if ($curr['status'] === 'APPROVED') return ['success' => false, 'message' => 'Transaksi sudah Approved.'];

        return $this->repo->updateStatus($id, 'REJECTED') ? ['success' => true, 'message' => 'Transaksi berhasil di-Reject'] : ['success' => false, 'message' => 'Gagal update database'];
    }

    public function getDetail($id)
    {
        $header = $this->repo->findById($id);
        if (!$header) return null;
        $details = $this->repo->findDetailsByKasbonId($id);
        
        $detailList = [];
        foreach($details as $row) {
            $detailList[] = [
                'account' => ['no' => $row['account_no'], 'name' => $row['account_name']],
                'detailNotes' => $row['notes'],
                'amount' => $row['amount'],
                'billAmount' => $row['bill_amount'],
                'jobOrder' => $row['job_order_id'] ? ['id' => $row['job_order_id'], 'number' => $row['jo_number']] : null
            ];
        }

        return [
            'id' => $header['id'],
            'number' => $header['transaction_number'],
            'transDate' => date('d/m/Y', strtotime($header['trans_date'])),
            'description' => $header['description'],
            'bank' => ['id' => $header['bank_id'], 'name' => $header['bank_name']],
            'status' => $header['status'],
            'detailAccount' => $detailList
        ];
    }

    public function getDashboardSummary($start, $end)
    {
        $summary = $this->repo->getDashboardSummary($start, $end);
        $joData = $this->repo->getJoPerformance($start, $end);
        
        $totalCost = floatval($summary['total_cost'] ?? 0);
        $totalBill = floatval($summary['total_bill'] ?? 0);
        
        $performance = [];
        foreach($joData as $row) {
            $cost = floatval($row['cost']);
            $bill = floatval($row['bill']);
            $gp = $bill - $cost;
            $performance[] = [
                'jo_number' => $row['jo_number'],
                'customer' => $row['customer_name'],
                'cost' => $cost,
                'bill' => $bill,
                'gp' => $gp,
                'margin' => $bill > 0 ? round(($gp / $bill) * 100, 1) : 0
            ];
        }

        return [
            'summary' => ['cost' => $totalCost, 'bill' => $totalBill, 'gp' => $totalBill - $totalCost],
            'jo_performance' => $performance
        ];
    }

    public function getJoExpenses($joNumber)
    {
        $jo = $this->repo->findJobOrderByNumber($joNumber);
        if (!$jo) return null;
        
        $expenses = $this->repo->getExpensesByJo($jo['id']);
        $totalCost = 0; $totalBill = 0;
        foreach($expenses as $e) { $totalCost += $e['cost']; $totalBill += $e['bill']; }
        
        $gp = $totalBill - $totalCost;
        $margin = $totalBill > 0 ? ($gp / $totalBill) * 100 : 0;

        return [
            'jo_info' => $jo,
            'expenses' => $expenses,
            'summary' => ['total_cost' => $totalCost, 'total_bill' => $totalBill, 'gross_profit' => $gp, 'margin' => round($margin, 2)]
        ];
    }
}