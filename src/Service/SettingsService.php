<?php
namespace App\Service;

use App\Repository\SettingsRepository;

class SettingsService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new SettingsRepository();
    }

    public function getSettings()
    {
        return $this->repo->getAll();
    }

    public function saveSettings(array $data)
    {
        if (empty($data)) {
            return ['success' => false, 'message' => 'Data tidak valid'];
        }
        
        try {
            $this->repo->updateBatch($data);
            return ['success' => true, 'message' => 'Konfigurasi berhasil disimpan'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()];
        }
    }
}