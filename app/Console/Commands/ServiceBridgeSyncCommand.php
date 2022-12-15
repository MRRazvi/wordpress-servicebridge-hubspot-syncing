<?php

namespace App\Console\Commands;

use App\Models\ServiceBridgeAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ServiceBridgeController;

class ServiceBridgeSyncCommand extends Command
{
    protected $signature = 'sb:sync';

    protected $description = 'Fetch all the data from service bridge and store into database.';

    public function handle()
    {
        Log::channel('sb-sync')->info('start');

        try {
            $service_bridge_accounts = ServiceBridgeAccount::all();

            foreach ($service_bridge_accounts as $service_bridge_account) {
                $client = new ServiceBridgeController(
                        $service_bridge_account->user_id,
                        $service_bridge_account->user_pass
                );

                $client->login();

                Log::channel('sb-sync')->info('sb_account:start', ['id' => $service_bridge_account->id, 'user_id' => $service_bridge_account->user_id]);
                $client->get_estimates();
                $client->get_work_orders();
                Log::channel('sb-sync')->info('sb_account:end');
            }
        } catch (\Exception $e) {
            Log::channel('sb-sync')->error('handle', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('sb-sync')->info('end');
    }
}
