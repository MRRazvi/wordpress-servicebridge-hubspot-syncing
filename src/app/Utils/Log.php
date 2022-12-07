<?php

namespace App\Utils;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log
{
    public $log;

    public function __construct()
    {
        $this->log = new Logger('app');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '../../storage/logs/app.log', Level::Debug));
    }

    public function info($message)
    {
        $this->log->info($message);
    }

    public function warning($message)
    {
        $this->log->warning($message);
    }

    public function error($message)
    {
        $this->log->error($message);
    }
}