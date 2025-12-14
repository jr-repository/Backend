<?php
namespace App\Service;

use App\Service\AccurateClient;
use App\Utils\ActivityLogger;

class TransferService
{
    private $accurate;

    public function __construct()
    {
        $this->accurate = new AccurateClient();
    }

    public function getList($keyword)
    {
        $params = [
            'fields' => 'id,number,transDate,description,fromBankAmount,status,fromBank,toBank', 
            'sp.pageSize' => 50,
            'sp.sort' => 'transDate|desc'
        ];
        if (!empty($keyword)) {
            $params['filter.number.op'] = 'CONTAIN';
            $params['filter.number.val[0]'] = $keyword;
        }

        $res = json_decode($this->accurate->call('/bank-transfer/list.do', 'GET', $params), true);
        $data = [];
        if (isset($res['s']) && $res['s']) {
            foreach ($res['d'] as $row) {
                $data[] = [
                    'id' => $row['id'],
                    'number' => $row['number'],
                    'transDate' => $row['transDate'],
                    'description' => $row['description'] ?? '',
                    'amount' => $row['fromBankAmount'] ?? 0,
                    'status' => $row['status'] ?? 'Unknown',
                    'fromBankName' => $row['fromBank']['name'] ?? '-',
                    'toBankName' => $row['toBank']['name'] ?? '-'
                ];
            }
        }
        return $data;
    }

    public function save($data, $userId, $userName)
    {
        $amount = floatval($data['amount']);
        $payload = [
            'transDate' => $data['transDate'], 
            'branchId' => $this->accurate->getConfig('branch_id'), 
            'fromBankId' => $data['fromBankId'], 
            'toBankId' => $data['toBankId'],    
            'fromBankAmount' => $amount, 
            'description' => $data['description'] ?? 'Transfer via Web App'
        ];

        $res = $this->accurate->call('/bank-transfer/save.do', 'POST', $payload);
        $json = json_decode($res, true);

        if (isset($json['s']) && $json['s']) {
            $trxNo = $json['r']['number'] ?? 'UNKNOWN';
            ActivityLogger::log($userId, $userName, 'BANK_TRANSFER', 'CREATE', $trxNo, $amount);
        }
        return $res; // Raw JSON string from Accurate
    }

    public function getDetail($id)
    {
        return $this->accurate->call('/bank-transfer/detail.do', 'GET', ['id' => $id]);
    }

    public function getGlAccounts($keyword, $type)
    {
        $params = ['fields' => 'id,name,no,accountType,parentNode', 'pageSize' => 50, 'sp.sort' => 'no'];
        if (!empty($keyword)) {
            $params['filter.keywords.op'] = 'CONTAIN';
            $params['filter.keywords.val[0]'] = $keyword;
        } elseif ($type === 'CASH_BANK') {
            $params['filter.accountType.op'] = 'EQUAL';
            $params['filter.accountType.val[0]'] = 'CASH_BANK';
        } else {
            $params['pageSize'] = 100;
        }

        $res = json_decode($this->accurate->call('/glaccount/list.do', 'GET', $params), true);
        $clean = [];
        if (isset($res['d'])) {
            foreach ($res['d'] as $acc) {
                if (isset($acc['parentNode']) && $acc['parentNode']) continue;
                if ($type === 'CASH_BANK') {
                    if ($acc['accountType'] !== 'CASH_BANK') continue;
                } else {
                    if (in_array($acc['accountType'], ['CASH_BANK', 'ACCOUNT_RECEIVABLE', 'ACCOUNT_PAYABLE'])) continue;
                }
                $clean[] = [
                    'id' => $acc['id'],
                    'no' => $acc['no'],
                    'name' => $acc['name'],
                    'accountType' => $acc['accountType'],
                    'label' => $acc['no'] . ' - ' . $acc['name']
                ];
            }
        }
        return ['s' => true, 'd' => $clean, 'count' => count($clean)];
    }
}