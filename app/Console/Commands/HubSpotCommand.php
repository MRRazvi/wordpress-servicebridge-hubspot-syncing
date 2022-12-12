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
        dd($hs->get_contact('mona.nijhof@gmail.com'));

        $sb_accounts = $this->get_sb_accounts();

        $estimates = Estimate::where('synced', false)->get();
        foreach ($estimates as $estimate) {
            $sb = $sb_accounts[$estimate->sb_account_id];
            $data = json_decode($estimate->blob);

            $hs_contact = $hs->get_contact($data->Contact->Email);
            if ($hs_contact) {
                dump("has contact");
            } else {
                // if ($estimate->status != 'FINISHED')
                //     continue;

                // shmidy911@hotmail.com
                $sb_contact = $sb->get_contact($data->Contact->Id);
                $sb_location = $sb->get_location($data->Location->Id);

                $_ = [
                    'firstname' => $sb_contact->FirstName,
                    'lastname' => $sb_contact->LastName,
                    'email' => $sb_contact->Email,
                    'phone' => $sb_contact->CellPhoneNumber ?? $sb_contact->PhoneNumber ?? '',
                    'address' => $sb_location->AddressLine1,
                    'city' => $sb_location->City,
                    'zip' => $sb_location->PostalCode,
                    'lifecyclestage' => '',
                    'status_from_sb' => 'Har fått tilbud',
                    'notat_om_aktivitet_i_service_bridge' => sprintf('%s - %s - %s', $data->EstimateNumber, $sb_location->AddressLine1, $data->Description),
                    'company' => $data->Customer->Name
                ];

                $contact = $hs->create_contact($_);
                dd($contact);
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
