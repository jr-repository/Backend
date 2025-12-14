<?php
namespace App\Service;

use App\Repository\JobOrderRepository;
use App\Service\AccurateClient;
use App\Utils\ActivityLogger;
use App\Core\Database;
use DateTime;

class JobOrderService
{
    private $repo;
    private $accurate;

    public function __construct()
    {
        $this->repo = new JobOrderRepository();
        $this->accurate = new AccurateClient();
    }

    public function getList($keyword)
    {
        $rows = $this->repo->findAll($keyword);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => $row['id'],
                'number' => $row['transaction_number'],
                'transDate' => date('d/m/Y', strtotime($row['trans_date'])),
                'customerName' => $row['customer_name'] ?? $row['customer_no'],
                'pic' => $row['pic'],
                'description' => $row['description'],
                'amount' => 0,
                'status' => $row['status']
            ];
        }
        return $data;
    }

    public function getDetail($id)
    {
        $header = $this->repo->findById($id);
        if (!$header) return null;

        $items = $this->repo->findItemsByJobOrderId($id);
        $detailItems = [];
        foreach ($items as $row) {
            $detailItems[] = [
                'item' => [
                    'no' => $row['item_no'],
                    'name' => $row['item_name']
                ],
                'quantity' => $row['quantity'],
                'itemUnit' => ['name' => 'PCS']
            ];
        }

        return [
            'id' => $header['id'],
            'number' => $header['transaction_number'],
            'transDate' => date('d/m/Y', strtotime($header['trans_date'])),
            'customer' => [
                'customerNo' => $header['customer_no'],
                'name' => $header['customer_name']
            ],
            'description' => $header['description'],
            'pic' => $header['pic'],
            'status' => $header['status'],
            'detailItem' => $detailItems
        ];
    }

    public function saveTransaction($data, $userId, $userName)
    {
        $dateObj = DateTime::createFromFormat('Y-m-d', $data['transDate']);
        $formattedDate = $dateObj ? $dateObj->format('d/m/Y') : date('d/m/Y');

        $payload = [
            'transDate' => $formattedDate,
            'branchId' => $this->accurate->getConfig('branch_id'),
            'customerNo' => $data['customerNo'],
            'number' => $data['number'],
            'description' => $data['description'] ?? '',
        ];

        foreach ($data['detailItem'] as $index => $item) {
            $warehouse = $item['warehouseName'] ?? $this->accurate->getConfig('warehouse');
            $payload["detailItem[{$index}].itemNo"] = $item['itemNo'];
            $payload["detailItem[{$index}].quantity"] = $item['quantity'];
            $payload["detailItem[{$index}].warehouseName"] = $warehouse;
        }

        $resJson = $this->accurate->call('/job-order/save.do', 'POST', $payload);
        $resArr = json_decode($resJson, true);

        $accurateId = null;
        $status = 'Accurate Failed';

        if (isset($resArr['s']) && $resArr['s'] === true) {
            $accurateId = $resArr['r']['id'] ?? null;
            $status = 'Submitted';
        }

        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();

        try {
            $saveData = [
                'trxNo' => $data['number'],
                'transDate' => $data['transDate'],
                'custNo' => $data['customerNo'],
                'custName' => $data['customerName'] ?? '',
                'pic' => $data['pic'] ?? '',
                'desc' => $data['description'] ?? '',
                'status' => $status,
                'accurateId' => $accurateId
            ];

            $jobOrderId = $this->repo->create($saveData);

            foreach ($data['detailItem'] as $item) {
                $this->repo->addItem($jobOrderId, [
                    'itemNo' => $item['itemNo'],
                    'itemName' => $item['itemName'] ?? '',
                    'quantity' => $item['quantity']
                ]);
            }

            $db->commit();
            
            if ($status === 'Submitted') {
                ActivityLogger::log($userId, $userName, 'JOB_ORDER', 'CREATE', $data['number'], 0);
            }

            return $resJson;

        } catch (\Exception $e) {
            $db->rollback();
            return json_encode(['s' => false, 'd' => ['message' => 'System Error: ' . $e->getMessage()]]);
        }
    }

    public function getMasterCustomers()
    {
        return $this->accurate->call('/customer/list.do', 'GET', ['fields' => 'id,name,customerNo', 'pageSize' => 100]);
    }

    public function getMasterItems()
    {
        return $this->accurate->call('/item/list.do', 'GET', ['fields' => 'id,name,no,unitName,itemType', 'pageSize' => 100]);
    }
}