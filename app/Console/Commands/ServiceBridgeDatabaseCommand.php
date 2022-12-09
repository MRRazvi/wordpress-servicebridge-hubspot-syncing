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
            $account_id = $service_bridge_account->id;
            $user_id = $service_bridge_account->user_id;
            $user_pass = $service_bridge_account->user_pass;

            $sb = new ServiceBridgeController($user_id, $user_pass);
            $sb->login();
            $estimates = $sb->get_estimates();
            $work_orders = $sb->get_work_orders();

            foreach ($estimates as $_estimate) {
                foreach ($_estimate['value'] as $estimate) {
                    Estimate::updateOrCreate([
                        'estimate_id' => $estimate->Id,
                        'version' => $estimate->Metadata->Version
                    ], [
                            'sb_account_id' => $account_id,
                            'status' => $estimate->Status,
                            'blob' => json_encode($estimate),
                            'created_at' => $estimate->Metadata->CreatedOn,
                            'updated_at' => $estimate->Metadata->UpdatedOn
                        ]);
                }
            }

            foreach ($work_orders as $_work_order) {
                foreach ($_work_order['value'] as $work_order) {
                    WorkOrder::updateOrCreate([
                        'work_order_id' => $work_order->Id,
                        'version' => $work_order->Metadata->Version
                    ], [
                            'sb_account_id' => $account_id,
                            'status' => $work_order->Status,
                            'blob' => json_encode($work_order),
                            'created_at' => $work_order->Metadata->CreatedOn,
                            'updated_at' => $work_order->Metadata->UpdatedOn
                        ]);
                }
            }
        }
    }
}
