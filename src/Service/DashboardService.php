<?php
namespace App\Service;

use App\Utils\Cache;
use App\Service\AccurateClient;

class DashboardService
{
    private $accurate;

    public function __construct()
    {
        $this->accurate = new AccurateClient();
    }

    public function getDashboardData($fromDate, $toDate)
    {
        $cacheKey = "dashboard_data_{$fromDate}_{$toDate}";
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        $response_data = [
            'summary' => [
                'total_cash' => 0,
                'total_ar' => 0,
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0
            ],
            'chart_bank' => [],
            'chart_pl' => [],
            'recent_invoices' => [],
            'recent_expenses' => []
        ];

        $resCash = json_decode($this->accurate->call('/glaccount/list.do', 'GET', [
            'fields' => 'id,no,name,balance',
            'filter.accountType.op' => 'EQUAL',
            'filter.accountType.val[0]' => 'CASH_BANK',
            'asOfDate' => $toDate, 
            'sp.pageSize' => 50
        ]), true);

        if (isset($resCash['d']) && is_array($resCash['d'])) {
            foreach ($resCash['d'] as $acc) {
                $bal = floatval($acc['balance'] ?? 0);
                $response_data['summary']['total_cash'] += $bal;
                if ($bal != 0) {
                    $response_data['chart_bank'][] = ['name' => $acc['name'], 'value' => $bal];
                }
            }
        }

        $resAR = json_decode($this->accurate->call('/glaccount/list.do', 'GET', [
            'fields' => 'id,no,name,balance',
            'filter.accountType.op' => 'EQUAL',
            'filter.accountType.val[0]' => 'ACCOUNT_RECEIVABLE',
            'asOfDate' => $toDate,
            'sp.pageSize' => 50
        ]), true);

        if (isset($resAR['d']) && is_array($resAR['d'])) {
            foreach ($resAR['d'] as $acc) {
                $response_data['summary']['total_ar'] += floatval($acc['balance'] ?? 0);
            }
        }

        $resPL = json_decode($this->accurate->call('/glaccount/get-pl-account-amount.do', 'GET', [
            'fromDate' => $fromDate,
            'toDate' => $toDate
        ]), true);

        if (isset($resPL['d']) && is_array($resPL['d'])) {
            foreach ($resPL['d'] as $item) {
                if ($item['lvl'] == 1) {
                    if ($item['accountType'] === 'REVENUE') {
                        $response_data['summary']['revenue'] += $item['amount'];
                    } elseif (in_array($item['accountType'], ['EXPENSE', 'OTHER_EXPENSE', 'COST_OF_GOOD_SOLD'])) {
                        $response_data['summary']['expense'] += $item['amount'];
                    }
                }
            }
        }
        
        $response_data['summary']['net_profit'] = $response_data['summary']['revenue'] - $response_data['summary']['expense'];
        $response_data['chart_pl'] = [
            ['name' => 'Revenue', 'value' => $response_data['summary']['revenue']],
            ['name' => 'Expense', 'value' => $response_data['summary']['expense']]
        ];

        $resRecentInv = json_decode($this->accurate->call('/sales-invoice/list.do', 'GET', [
            'fields' => 'number,transDate,customer,totalAmount,status',
            'filter.transDate.op' => 'BETWEEN',
            'filter.transDate.val[0]' => $fromDate,
            'filter.transDate.val[1]' => $toDate,
            'sp.pageSize' => 5,
            'sp.sort' => 'transDate|desc'
        ]), true);

        if (isset($resRecentInv['d']) && is_array($resRecentInv['d'])) {
            foreach($resRecentInv['d'] as $inv) {
                $response_data['recent_invoices'][] = [
                    'number' => $inv['number'] ?? '-',
                    'date' => $inv['transDate'] ?? '-',
                    'customer' => $inv['customer']['name'] ?? '-',
                    'amount' => $inv['totalAmount'] ?? 0,
                    'status' => $inv['status'] ?? '' 
                ];
            }
        }

        $resRecentExp = json_decode($this->accurate->call('/other-payment/list.do', 'GET', [
            'fields' => 'number,transDate,description,amount',
            'filter.transDate.op' => 'BETWEEN',
            'filter.transDate.val[0]' => $fromDate,
            'filter.transDate.val[1]' => $toDate,
            'sp.pageSize' => 5,
            'sp.sort' => 'transDate|desc'
        ]), true);
        
        if (isset($resRecentExp['d']) && is_array($resRecentExp['d'])) {
            foreach($resRecentExp['d'] as $exp) {
                $response_data['recent_expenses'][] = [
                    'number' => $exp['number'] ?? '-',
                    'date' => $exp['transDate'] ?? '-',
                    'desc' => $exp['description'] ?? 'Pengeluaran',
                    'amount' => $exp['amount'] ?? 0
                ];
            }
        }

        Cache::set($cacheKey, $response_data, 600); 
        return $response_data;
    }
}