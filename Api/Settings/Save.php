<?php
require_once __DIR__ . '/../../autoload.php';
use App\Controller\SettingsController;
(new SettingsController())->save();