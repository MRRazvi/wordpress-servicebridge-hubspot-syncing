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
        // $deal = $hs->get_deal(11317921395);
        $deal = $hs->create_deal([
            // 'associatedvids' => '4090301',
            'dealname' => 'deal name test',
            'pipeline' => 'default',
            'amount' => '123',
            'dealtype' => 'newbusiness',
            // 'kilde' => 'marketing'
        ]);
        dd($deal);

        $sb_accounts = $this->get_sb_accounts();

        $this->sync_estimates($hs, $sb_accounts);
        $this->sync_work_orders($sb_accounts);
    }

    private function sync_estimates($hs, $sb_accounts)
    {
        $estimates = Estimate::where('synced', false)->get();
        foreach ($estimates as $estimate) {
            $sb = $sb_accounts[$estimate->sb_account_id];
            $data = $sb->get_estimate($estimate->estimate_id);

            // no need of not finished statuses
            if ($data->Status != 'Finished' && $data->Status != 'WonEstimate' && $data->Status != 'LostEstimate') {
                $estimate->synced = true;
                $estimate->save();
                continue;
            }

            $hs_contact = $hs->get_contact($data->Contact->Email);
            if ($hs_contact) {
                // update contact on hubspot
                dump("has contact");
            } else {
                dump("create contact");
                continue;
                // create contact if not exist on hubspot
                $sb_customer = $sb->get_customer($data->Customer->Id);
                $hs_contact = $hs->create_contact([
                    'firstname' => $sb_customer->DefaultServiceLocation->PrimaryContact->FirstName,
                    'lastname' => $sb_customer->DefaultServiceLocation->PrimaryContact->LastName,
                    'email' => $sb_customer->DefaultServiceLocation->PrimaryContact->Email,
                    'phone' => $sb_customer->DefaultServiceLocation->PrimaryContact->CellPhoneNumber ?? $sb_customer->DefaultServiceLocation->PrimaryContact->PhoneNumber ?? '',
                    'address' => $sb_customer->DefaultServiceLocation->AddressLine1,
                    'city' => $sb_customer->DefaultServiceLocation->City,
                    'zip' => $sb_customer->DefaultServiceLocation->PostalCode,
                    'lifecyclestage' => count($data->EstimateLines ?? []) > 0 ? 'customer' : 'opportunity',
                    'status_from_sb' => $this->get_status_of_estimate_for_hs($data),
                    'notat_om_aktivitet_i_service_bridge' => sprintf('%s - %s - %s', $data->EstimateNumber, $sb_customer->DefaultServiceLocation->AddressLine1, $data->Description),
                    'company' => $sb_customer->CompanyName ?? ''
                ]);

                $hs_contact_id = $hs_contact['id'];

                dd($hs_contact_id);
            }
        }
    }

    private function sync_work_orders($sb_accounts)
    {
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

    private function get_status_of_estimate_for_hs($estimate)
    {
        switch ($estimate->Status) {
            case 'Won Estimate':
                return 'EST Won';
            case 'Lost Estimate':
                return 'EST Lost';
            case 'Finished':
                return 'EST Finish';
            default:
                return '';
        }
    }

    private function get_estimate_deal_price($estimate)
    {
        $total = 0;
        foreach ($estimate->EstimateLines as $line) {
            $total += ($line->Price * $line->Quantity) + $line->Tax;
        }

        if ($total == 0)
            return 0;

        $total += 25 * $total / 100; // 25% tax
        return printf("%.2f", $total);
    }
}
