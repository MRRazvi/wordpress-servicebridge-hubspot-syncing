<?php

namespace App\Console\Commands;

use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\ServiceBridgeController;
use App\Models\Estimate;
use App\Models\ServiceBridgeAccount;
use App\Models\WorkOrder;
use Illuminate\Console\Command;

class HubSpotCommand extends Command
{
    protected $signature = 'hs:sync';

    protected $description = 'Check database for any change and update it on HubSpot.';

    public function handle()
    {
        $hs = new HubSpotController(env('HUBSPOT_API_KEY'));
        $sb_accounts = $this->get_sb_accounts();

        $this->sync_estimates($hs, $sb_accounts);
        $this->sync_work_orders($hs, $sb_accounts);
    }

    private function sync_estimates($hs, $sb_accounts)
    {
        $estimates = Estimate::where('synced', false)->get();
        foreach ($estimates as $estimate) {
            try {
                $sb = $sb_accounts[$estimate->sb_account_id];
                $data = $sb->get_estimate($estimate->estimate_id);

                $this->get_estimate_deal_price($data);

                // no need of not finished statuses
                if ($data->Status != 'Finished' && $data->Status != 'WonEstimate' && $data->Status != 'LostEstimate') {
                    $estimate->synced = true;
                    $estimate->save();
                    continue;
                }

                $hs_contact = $hs->get_contact($data->Contact->Email);
                $sb_customer = $sb->get_customer($data->Customer->Id);
                $sb_customer_contact = $sb_customer->DefaultServiceLocation->PrimaryContact;
                $sb_customer_location = $sb_customer->DefaultServiceLocation;

                $input = [
                    'firstname' => $sb_customer_contact->FirstName,
                    'lastname' => $sb_customer_contact->LastName,
                    'email' => $sb_customer_contact->Email,
                    'phone' => $sb_customer_contact->CellPhoneNumber ?? $sb_customer_contact->PhoneNumber ?? '',
                    'address' => $sb_customer_location->AddressLine1,
                    'city' => $sb_customer_location->City,
                    'zip' => $sb_customer_location->PostalCode,
                    'lifecyclestage' => count($data->EstimateLines ?? []) > 0 ? 'customer' : 'opportunity',
                    'status_from_sb' => $this->get_status_of_estimate_for_hs($data),
                    'notat_om_aktivitet_i_service_bridge' => sprintf('%s - %s - %s', $data->EstimateNumber, $sb_customer_location->AddressLine1, $data->Description),
                    'company' => $sb_customer->CompanyName ?? ''
                ];

                if ($hs_contact) {
                    dump("update contact", $estimate->estimate_id);
                    $hs_contact = $hs->update_contact($hs_contact['id'], $input);
                } else {
                    dump("creatge contact", $estimate->estimate_id);
                    $hs_contact = $hs->create_contact($input);

                    if (isset($data->EstimateLines)) {
                        $hs->create_deal($hs_contact['id'], [
                            'dealname' => sprintf('%s - %s - %s', $data->EstimateNumber, $sb_customer_location->AddressLine1, $data->Description),
                            'amount' => $this->get_estimate_deal_price($data),
                            'pipeline' => 'default',
                            'dealtype' => 'newbusiness',
                            'dealstage' => 'contractsent',
                            'closedate' => now()->addMonth()->valueOf()
                        ]);
                    }
                }

                $estimate->synced = true;
                $estimate->save();
            } catch (\Exception $e) {
                dump("error: " . $e->getMessage());
            }
        }
    }

    private function sync_work_orders($hs, $sb_accounts)
    {
        $work_orders = WorkOrder::where('synced', false)->get();
        foreach ($work_orders as $work_order) {
            try {
                $sb = $sb_accounts[$work_order->sb_account_id];
                $data = $sb->get_work_order($work_order->work_order_id);

                // only completed work orders need to be sync
                if ($data->Status != 'Completed') {
                    $work_order->synced = true;
                    $work_order->save();
                    continue;
                }

                $hs_contact = $hs->get_contact($data->Contact->Email);
                $sb_work_order = $sb->get_customer($data->Customer->Id);
                $sb_work_order_contact = $sb_work_order->DefaultServiceLocation->PrimaryContact;
                $sb_work_order_location = $sb_work_order->DefaultServiceLocation;

                $input = [
                    'firstname' => $sb_work_order_contact->FirstName,
                    'lastname' => $sb_work_order_contact->LastName,
                    'email' => $sb_work_order_contact->Email,
                    'phone' => $sb_work_order_contact->CellPhoneNumber ?? $sb_work_order_contact->PhoneNumber ?? '',
                    'address' => $sb_work_order_location->AddressLine1,
                    'city' => $sb_work_order_location->City,
                    'zip' => $sb_work_order_location->PostalCode,
                    'lifecyclestage' => count($data->WorkOrderLines ?? []) > 0 ? 'customer' : 'opportunity',
                    'status_from_sb' => $this->get_status_of_work_order_for_hs($data),
                    'notat_om_aktivitet_i_service_bridge' => sprintf('%s - %s - %s', $data->WorkOrderNumber, $sb_work_order_location->AddressLine1, $data->Description),
                    'company' => $sb_work_order->CompanyName ?? ''
                ];

                if ($hs_contact) {
                    dump("update contact", $work_order->work_order_id);
                    $hs->update_contact($hs_contact['id'], $input);
                } else {
                    dump("create contact", $work_order->work_order_id);
                    $hs->create_contact($input);
                }

                $work_order->synced = true;
                $work_order->save();
            } catch (\Exception $e) {
                dump('error: ' . $e->getMessage());
            }
        }

        dump("all work orders synced successfully");
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

    private function get_status_of_work_order_for_hs($work_order)
    {
        if (preg_match('/-/mui', $work_order->WorkOrderNumber))
            return 'WO Not recurring';

        return 'WO Recurring';
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

        return sprintf("%.2f", $total);
    }
}
