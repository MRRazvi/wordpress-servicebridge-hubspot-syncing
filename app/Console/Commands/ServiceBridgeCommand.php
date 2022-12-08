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
        dump($sb->session_key);

        $sb->get_estimates();

        echo "1";
    }
}
