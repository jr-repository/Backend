<?php
require_once __DIR__ . '/../../../autoload.php';
use App\Controller\MasterDataController;
(new MasterDataController())->listTaxes();