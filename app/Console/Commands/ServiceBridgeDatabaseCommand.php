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
            $estimates = $client->get_estimates();
            $work_orders = $client->get_work_orders();

            foreach ($estimates as $_estimate) {
                foreach ($_estimate['value'] as $estimate) {
                    $e = Estimate::where('estimate_id', $estimate->Id)->count();

                    if ($e) {
                        $e = Estimate::where([
                            'estimate_id' => $estimate->Id,
                            'version' => $estimate->Metadata->Version
                        ])->count();

                        if ($e == 0) {
                            Estimate::where('estimate_id', $estimate->Id)
                                ->update([
                                    'status' => $estimate->Status,
                                    'version' => $estimate->Metadata->Version,
                                    'synced' => false,
                                    'blob' => json_encode($estimate),
                                    'created_at' => $estimate->Metadata->CreatedOn,
                                    'updated_at' => $estimate->Metadata->UpdatedOn
                                ]);
                        }
                    } else {
                        Estimate::create([
                            'estimate_id' => $estimate->Id,
                            'sb_account_id' => $service_bridge_account->id,
                            'status' => $estimate->Status,
                            'version' => $estimate->Metadata->Version,
                            'synced' => false,
                            'blob' => json_encode($estimate),
                            'created_at' => $estimate->Metadata->CreatedOn,
                            'updated_at' => $estimate->Metadata->UpdatedOn
                        ]);
                    }
                }
            }

            foreach ($work_orders as $_work_order) {
                foreach ($_work_order['value'] as $work_order) {
                    $wo = WorkOrder::where('work_order_id', $work_order->Id)->count();

                    if ($wo) {
                        $wo = WorkOrder::where([
                            'work_order_id' => $work_order->Id,
                            'version' => $work_order->Metadata->Version
                        ])->count();

                        if ($wo == 0) {
                            WorkOrder::where('work_order_id', $work_order->Id)
                                ->update([
                                    'status' => $work_order->Status,
                                    'version' => $work_order->Metadata->Version,
                                    'synced' => false,
                                    'blob' => json_encode($work_order),
                                    'created_at' => $work_order->Metadata->CreatedOn,
                                    'updated_at' => $work_order->Metadata->UpdatedOn
                                ]);
                        }
                    } else {
                        WorkOrder::create([
                            'work_order_id' => $work_order->Id,
                            'sb_account_id' => $service_bridge_account->id,
                            'status' => $work_order->Status,
                            'version' => $work_order->Metadata->Version,
                            'synced' => false,
                            'blob' => json_encode($work_order),
                            'created_at' => $work_order->Metadata->CreatedOn,
                            'updated_at' => $work_order->Metadata->UpdatedOn
                        ]);
                    }
                }
            }
        }

        dump(1);
    }
}
