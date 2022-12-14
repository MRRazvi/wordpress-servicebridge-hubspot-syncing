<?php

namespace App\Console\Commands;

use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\ServiceBridgeController;
use App\Models\Estimate;
use App\Models\ServiceBridgeAccount;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Termwind\Components\Dd;

class HubSpotSyncCommand extends Command
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
        Log::channel('hs-sync')->info('sync_estimates:start');

        try {
            $estimates = Estimate::where('synced', false)->get();
            Log::channel('hs-sync')->info('count', ['count' => $estimates->count()]);

            foreach ($estimates as $estimate) {
                try {
                    $sb = $sb_accounts[$estimate->sb_account_id];
                    $data = $sb->get_estimate($estimate->estimate_id);

                    if ($data->Status != 'Finished' && $data->Status != 'WonEstimate' && $data->Status != 'LostEstimate') {
                        $estimate->synced = true;
                        $estimate->save();
                        continue;
                    }

                    $sb_customer = $sb->get_customer($data->Customer->Id);
                    $sb_customer_contact = $sb_customer->DefaultServiceLocation->PrimaryContact;
                    $sb_customer_location = $sb_customer->DefaultServiceLocation;
                    $deal_name = sprintf('%s - %s - %s', $data->EstimateNumber, $sb_customer_location->AddressLine1, $data->Description);

                    $input = [
                        'firstname' => $sb_customer_contact->FirstName,
                        'lastname' => $sb_customer_contact->LastName,
                        'email' => $sb_customer_contact->Email,
                        'phone' => empty($sb_customer_contact->CellPhoneNumber) ? $sb_customer_contact->PhoneNumber : $sb_customer_contact->CellPhoneNumber,
                        'address' => $sb_customer_location->AddressLine1,
                        'city' => $sb_customer_location->City,
                        'zip' => $sb_customer_location->PostalCode,
                        'lifecyclestage' => count($data->EstimateLines ?? []) > 0 ? 'customer' : 'opportunity',
                        'status_from_sb' => $this->get_status_of_estimate_for_hs($data->Status),
                        'notat_om_aktivitet_i_service_bridge' => $deal_name,
                        'job_status_in_service_bridge' => 'Estimate',
                        'company' => empty($sb_customer->CompanyName) ? $sb_customer->DisplayName : $sb_customer->CompanyName,
                    ];

                    if ($data->Status == 'Finished') {
                        $input['job_date_in_service_bridge'] = '';
                    }

                    $hs_contact_id = $hs->create_update_contact($sb_customer_contact->Email, $input);

                    if ($hs_contact_id) {
                        $deal = $hs->search_deal($hs_contact_id);
                        if ($deal) {
                            $hs->update_deal(
                                $deal->dealId,
                                [
                                    'dealname' => $deal_name,
                                    'amount' => $this->get_estimate_deal_price($data),
                                    'dealstage' => $this->get_deal_stage($data->Status),
                                    'kilde' => $this->get_marketing_campaign($data->MarketingCampaign->Name)
                                ]
                            );
                        } else {
                            if (isset($data->EstimateLines)) {
                                $hs->create_deal($hs_contact_id, [
                                    'dealname' => $deal_name,
                                    'amount' => $this->get_estimate_deal_price($data),
                                    'dealstage' => $this->get_deal_stage($data->Status),
                                    'pipeline' => 'default',
                                    'dealtype' => 'newbusiness',
                                    'closedate' => now()->addDays(14)->valueOf(),
                                    'kilde' => $this->get_marketing_campaign($data->MarketingCampaign->Name)
                                ]);
                            }
                        }

                        $estimate->synced = true;
                        $estimate->save();

                        Log::channel('hs-sync')->info('done', ['id' => $estimate->id, 'estimate' => $estimate->estimate_id]);
                    }
                } catch (\Exception $e) {
                    Log::channel('hs-sync')->error('sync_estimates', [
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'message' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('sync_estimates', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('hs-sync')->info('sync_estimates:end');
    }

    private function sync_work_orders($hs, $sb_accounts)
    {
        Log::channel('hs-sync')->info('sync_work_orders:start');

        try {
            $work_orders = WorkOrder::where('synced', false)->get();
            Log::channel('hs-sync')->info('count', ['count' => $work_orders->count()]);

            foreach ($work_orders as $work_order) {
                try {
                    $sb = $sb_accounts[$work_order->sb_account_id];
                    $data = $sb->get_work_order($work_order->work_order_id);

                    if ($data->Status != 'Completed') {
                        $work_order->synced = true;
                        $work_order->save();
                        continue;
                    }

                    $sb_customer = $sb->get_customer($data->Customer->Id);
                    $sb_customer_contact = $sb_customer->DefaultServiceLocation->PrimaryContact;
                    $sb_customer_location = $sb_customer->DefaultServiceLocation;
                    $deal_name = sprintf('%s - %s - %s', $data->WorkOrderNumber, $sb_customer_location->AddressLine1, $data->Description);

                    $input = [
                        'firstname' => $sb_customer_contact->FirstName,
                        'lastname' => $sb_customer_contact->LastName,
                        'email' => $sb_customer_contact->Email,
                        'phone' => empty($sb_customer_contact->CellPhoneNumber) ? $sb_customer_contact->PhoneNumber : $sb_customer_contact->CellPhoneNumber,
                        'address' => $sb_customer_location->AddressLine1,
                        'city' => $sb_customer_location->City,
                        'zip' => $sb_customer_location->PostalCode,
                        'lifecyclestage' => count($data->WorkOrderLines ?? []) > 0 ? 'customer' : 'opportunity',
                        'status_from_sb' => $this->get_status_of_estimate_for_hs($data->Status),
                        'notat_om_aktivitet_i_service_bridge' => $deal_name,
                        'job_status_in_service_bridge' => 'Estimate',
                        'company' => empty($sb_customer->CompanyName) ? $sb_customer->DisplayName : $sb_customer->CompanyName,
                    ];

                    $hs_contact_id = $hs->create_update_contact($sb_customer_contact->Email, $input);

                    if ($hs_contact_id) {
                        $work_order->synced = true;
                        $work_order->save();

                        Log::channel('hs-sync')->info('done', ['id' => $work_order->id, 'estimate' => $work_order->work_order_id]);
                    }
                } catch (\Exception $e) {
                    Log::channel('hs-sync')->error('sync_work_orders', [
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'message' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('sync_work_orders', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('hs-sync')->info('sync_work_orders:end');
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

    private function get_status_of_estimate_for_hs($status)
    {
        switch ($status) {
            case 'WonEstimate':
                return 'EST Won';
            case 'LostEstimate':
                return 'EST Lost';
            case 'Finished':
                return 'EST Finish';
            default:
                return '';
        }
    }

    private function get_deal_stage($status)
    {
        switch ($status) {
            case 'WonEstimate':
                return 'closedwon';
            case 'LostEstimate':
                return 'closedlost';
            default:
                return 'contractsent';
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

    private function get_marketing_campaign($campaign)
    {
        $campaigns = [
            'Møtebooking Activo',
            'Anbefalt',
            'Facebook',
            'Google',
            'Instagram',
            'Jeg tar selv æren for denne',
            'Linkedin',
            'Møtebooking',
            'Møtebooking Citero'
        ];

        if (!in_array($campaign, $campaigns)) {
            return '';
        }

        return $campaign;
    }
}
