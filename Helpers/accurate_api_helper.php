<?php
require_once __DIR__ . '/../autoload.php';
use App\Service\AccurateClient;

date_default_timezone_set('Asia/Jakarta');

if (!isset($accurateClientInstance)) {
    $accurateClientInstance = new AccurateClient();
}

if (!defined('BRANCH_ID')) {
    define('BRANCH_ID', $accurateClientInstance->getConfig('branch_id'));
}
if (!defined('WAREHOUSE_NAME')) {
    define('WAREHOUSE_NAME', $accurateClientInstance->getConfig('warehouse'));
}

function callAccurateApi($endpoint, $method = 'GET', $data = []) {
    global $accurateClientInstance;
    return $accurateClientInstance->call($endpoint, $method, $data);
}