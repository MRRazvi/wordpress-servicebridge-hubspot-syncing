<?php

namespace App\Console\Commands;

use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\ServiceBridgeController;
use App\Models\Estimate;
use App\Models\ServiceBridgeAccount;
use Illuminate\Console\Command;

class HubSpotCommand extends Command
{
    protected $signature = 'hs:sync';

    protected $description = 'Check database for any change and update it on HubSpot.';

    public function handle()
    {
        $hs = new HubSpotController(env('HUBSPOT_API_KEY'));
        $sb_accounts = $this->get_sb_accounts();

        $estimates = Estimate::where('synced', false)->get();
        foreach ($estimates as $estimate) {
            $sb = $sb_accounts[$estimate->sb_account_id];
            $data = json_decode($estimate->blob);

            $hs_contact = $hs->get_contact($data->Contact->Email);
            if ($hs_contact) {
                dump("has contact");
            } else {
                dump("no contact");
            }
        }
    }

    private function get_sb_accounts()
    {
        $data = [];

        $accounts = ServiceBridgeAccount::all();
        foreach ($accounts as $account) {
            $sb = new ServiceBridgeController($account->user_id, $account->user_pass);
            $sb->login();
            $data[$account->id] = $sb;
        }

        return $data;
    }
}
