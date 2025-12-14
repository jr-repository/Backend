<?php
namespace App\Service;

use App\Repository\MasterDataRepository;
use App\Core\Database;

class MasterDataService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new MasterDataRepository();
    }

    public function getTaxes()
    {
        $taxes = $this->repo->getAllTaxes();
        $data = [];
        foreach ($taxes as $row) {
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'rate' => floatval($row['rate']),
                'type' => $row['type'],
                'isDefault' => $row['is_default'] == 1
            ];
        }
        return $data;
    }

    public function saveTax($input)
    {
        $id = $input['id'] ?? 0;
        $name = $input['name'] ?? '';
        $rate = floatval($input['rate'] ?? 0);
        $type = $input['type'] ?? 'PPN';
        $isDefault = !empty($input['isDefault']) ? 1 : 0;

        if (empty($name)) {
            return ['success' => false, 'message' => 'Nama Pajak wajib diisi'];
        }

        $db = Database::getInstance()->getConnection();
        $db->begin_transaction();

        try {
            if ($isDefault) {
                $this->repo->resetDefaultTax($type);
            }

            if ($id > 0) {
                $this->repo->updateTax($id, $name, $rate, $type, $isDefault);
            } else {
                $this->repo->createTax($name, $rate, $type, $isDefault);
            }

            $db->commit();
            return ['success' => true, 'message' => 'Data Pajak tersimpan'];
        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteTax($id)
    {
        if ($this->repo->deleteTax($id)) {
            return ['success' => true, 'message' => 'Terhapus'];
        }
        return ['success' => false, 'message' => 'Gagal hapus'];
    }
}