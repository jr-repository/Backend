<?php
namespace App\Service;

use App\Service\AccurateClient;

class PelunasanService
{
    private $accurate;

    public function __construct()
    {
        $this->accurate = new AccurateClient();
    }

    public function save($data)
    {
        $totalPayment = 0;
        $payload = [
            'transDate' => $data['transDate'], 
            'customerNo' => $data['customerNo'],
            'bankId' => $data['bankId'],
            'branchId' => $this->accurate->getConfig('branch_id'),
            'description' => $data['description'] ?? 'Pelunasan via Web App'
        ];

        $idx = 0;
        foreach ($data['invoices'] as $inv) {
            $amount = floatval($inv['payAmount']);
            if ($amount <= 0) continue;
            $totalPayment += $amount;
            $payload["detailInvoice[{$idx}].invoiceId"] = $inv['invoiceId']; 
            $payload["detailInvoice[{$idx}].paymentAmount"] = $amount;
            $idx++;
        }

        if ($totalPayment <= 0) {
            return json_encode(['s' => false, 'message' => 'Total pembayaran tidak boleh nol']);
        }

        $payload['chequeAmount'] = $totalPayment;
        return $this->accurate->call('/sales-receipt/save.do', 'POST', $payload);
    }

    public function getOutstandingInvoices($customerNo, $keyword)
    {
        if (empty($customerNo)) return [];

        $params = [
            'fields' => 'id,number,transDate,totalAmount,outstanding',
            'sp.pageSize' => 50, 
            'filter.customer.customerNo.op' => 'EQUAL',
            'filter.customer.customerNo.val[0]' => $customerNo,
            'filter.status.op' => 'EQUAL',
            'filter.status.val[0]' => 'OUTSTANDING',
        ];

        if (!empty($keyword)) {
            $params['filter.number.op'] = 'CONTAIN';
            $params['filter.number.val[0]'] = $keyword;
        } else {
            $params['sp.sort'] = 'transDate|desc';
        }

        $res = json_decode($this->accurate->call('/sales-invoice/list.do', 'GET', $params), true);
        $data = [];
        
        if (isset($res['d'])) {
            foreach ($res['d'] as $inv) {
                $data[] = [
                    'id' => $inv['id'],
                    'number' => $inv['number'],
                    'date' => $inv['transDate'],
                    'amount' => $inv['totalAmount'],
                    'outstanding' => $inv['outstanding'] ?? $inv['totalAmount'] 
                ];
            }
        }
        return $data;
    }

    public function getCustomers()
    {
        $res = $this->accurate->call('/customer/list.do', 'GET', ['fields' => 'id,name,customerNo', 'pageSize' => 100]);
        $json = json_decode($res, true);
        return (isset($json['s']) && $json['s']) ? $json : null;
    }
}