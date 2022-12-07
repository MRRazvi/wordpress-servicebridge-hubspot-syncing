<?php

use Symfony\Component\Dotenv\Dotenv;
use App\Services\ServiceBridge;

include_once __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

$clientSB = new ServiceBridge('1');
$clientSB->login();
