<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ServiceBridgeController;

class ServiceBridgeCommand extends Command
{
    protected $signature = 'sync:sb';

    protected $description = 'Fetch all the data from service bridge and store into database.';

    public function handle()
    {
        $sb = new ServiceBridgeController();

        $sb->login();
        $estimates = $sb->get_estimates();
        $work_orders = $sb->get_work_orders();

        dump($estimates, $work_orders);
    }
}
