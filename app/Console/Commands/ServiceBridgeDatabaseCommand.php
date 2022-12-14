<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Models\ServiceBridgeAccount;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use App\Http\Controllers\ServiceBridgeController;

class ServiceBridgeDatabaseCommand extends Command
{
    protected $signature = 'sb:database';

    protected $description = 'Fetch all the data from service bridge and store into database.';

    public function handle()
    {
        $service_bridge_accounts = ServiceBridgeAccount::all();

        foreach ($service_bridge_accounts as $service_bridge_account) {
            $client = new ServiceBridgeController(
                    $service_bridge_account->user_id,
                    $service_bridge_account->user_pass
            );

            $client->login();
            $client->get_estimates();
            $client->get_work_orders();
        }
    }
}
