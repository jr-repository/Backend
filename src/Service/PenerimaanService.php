<?php
namespace App\Service;

use App\Service\AccurateClient;
use App\Utils\ActivityLogger;

class PenerimaanService
{
    private $accurate;

    public function __construct()
    {
        $this->accurate = new AccurateClient();
    }

    public function getList($q)
    {
        $params = ['fields' => 'id,number,transDate,description,status,amount', 'sp.pageSize' => 50, 'sp.sort' => 'transDate|desc'];
        if (!empty($q)) {
            $params['filter.number.op'] = 'CONTAIN';
            $params['filter.number.val[0]'] = $q;
        }
        $res = json_decode($this->accurate->call('/other-deposit/list.do', 'GET', $params), true);
        $data = [];
        if (isset($res['d'])) {
            foreach ($res['d'] as $row) {
                $data[] = [
                    'id' => $row['id'],
                    'number' => $row['number'],
                    'transDate' => $row['transDate'],
                    'description' => $row['description'] ?? '',
                    'amount' => $row['amount'] ?? 0,
                    'status' => $row['status'] ?? 'Unknown'
                ];
            }
        }
        return $data;
    }

    public function save($data, $userId, $userName)
    {
        $payload = [
            'transDate' => $data['transDate'], 
            'bankId' => $data['bankId'],       
            'branchId' => $this->accurate->getConfig('branch_id'), 
            'description' => $data['description'] ?? 'Penerimaan Lain via Web App'
        ];

        $totalAmount = 0;
        foreach ($data['detailAccount'] as $idx => $item) {
            if (empty($item['accountNo']) || empty($item['amount'])) continue;
            $amount = floatval($item['amount']);
            $totalAmount += $amount;
            $payload["detailAccount[{$idx}].accountNo"] = trim($item['accountNo']);
            $payload["detailAccount[{$idx}].amount"] = $amount;
            if (!empty($item['detailNotes'])) {
                $payload["detailAccount[{$idx}].detailNotes"] = $item['detailNotes'];
            }
        }

        $res = $this->accurate->call('/other-deposit/save.do', 'POST', $payload);
        $json = json_decode($res, true);

        if (isset($json['s']) && $json['s']) {
            $trxNo = $json['r']['number'] ?? 'UNKNOWN';
            ActivityLogger::log($userId, $userName, 'OTHER_DEPOSIT', 'CREATE', $trxNo, $totalAmount);
        }
        return $res;
    }

    public function getDetail($id)
    {
        return $this->accurate->call('/other-deposit/detail.do', 'GET', ['id' => $id]);
    }
}