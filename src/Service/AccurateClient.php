<?php
namespace App\Service;

use App\Repository\SettingsRepository;
use App\Utils\Logger;
use App\Core\Response;

class AccurateClient
{
    private $settingsRepo;
    private $config = [];

    public function __construct()
    {
        $this->settingsRepo = new SettingsRepository();
        date_default_timezone_set('Asia/Jakarta');
    }

    private function loadConfig()
    {
        if (empty($this->config)) {
            $settings = $this->settingsRepo->getAll();
            
            $this->config = [
                'host' => $settings['accurate_host']['setting_value'] ?? 'https://zeus.accurate.id',
                'token' => $settings['api_token']['setting_value'] ?? '',
                'secret' => $settings['signature_secret']['setting_value'] ?? '',
                'branch_id' => $settings['branch_id']['setting_value'] ?? 50,
                'warehouse' => $settings['warehouse_name']['setting_value'] ?? 'GD. JAKARTA'
            ];

            if (empty($this->config['token']) || empty($this->config['secret'])) {
                Logger::write('CRITICAL', 'Missing Accurate API Configuration');
                Response::error('Konfigurasi API Accurate belum lengkap.');
            }
        }
    }

    private function generateHeaders()
    {
        $this->loadConfig();
        $timestamp = date('d/m/Y H:i:s');
        $signature = base64_encode(hash_hmac('sha256', $timestamp, trim($this->config['secret']), true));

        return [
            'Authorization: Bearer ' . $this->config['token'],
            'X-Api-Timestamp: ' . $timestamp,
            'X-Api-Signature: ' . $signature,
            'Content-Type: application/x-www-form-urlencoded'
        ];
    }

    public function call($endpoint, $method = 'GET', $data = [])
    {
        $this->loadConfig();
        $url = $this->config['host'] . '/accurate/api' . $endpoint;
        
        if ($method === 'GET' && !empty($data)) {
            $query_params = http_build_query($data);
            $url .= '?' . $query_params;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->generateHeaders());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::write('ERROR', 'CURL Failure', ['error' => $error]);
            Response::error('CURL Connection Error: ' . $error);
        }

        Logger::write('INFO', 'Accurate API Call', [
            'url' => $url,
            'method' => $method,
            'http_code' => $httpCode
        ]);

        return $response;
    }

    public function getConfig($key)
    {
        $this->loadConfig();
        return $this->config[$key] ?? null;
    }
}