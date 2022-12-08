<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use App\Http\Controllers\ServiceBridgeController;

class ServiceBridgeCommand extends Command
{
    protected $signature = 'sync:sb';

    protected $description = 'Fetch all the data from service bridge and store into database.';

    public function handle()
    {
        // $sb = new ServiceBridgeController();
        // $sb->login();
        // $estimates = $sb->get_estimates();
        // $work_orders = $sb->get_work_orders();

        $estimates_finished = Estimate::whereIn('Status', ['Finished', 'WonEstimate', 'LostEstimate'])->get();
        $estimates_not_finished = Estimate::where('Status', '!=', 'Finished')->get();

        $work_orders_completed = WorkOrder::where('status', 'Completed')->get();
        $work_orders_not_completed = WorkOrder::where('status', '!=', 'Completed')->get();

        dump($estimates_finished, $estimates_not_finished, $work_orders_completed, $work_orders_not_completed);
    }
}
