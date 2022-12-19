<?php

namespace App\Console\Commands;

use App\Http\Controllers\HubSpotController;
use App\Http\Controllers\ServiceBridgeController;
use App\Models\Estimate;
use App\Models\HubSpotOwner;
use App\Models\ServiceBridgeAccount;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class HubSpotSyncCommand extends Command
{
    protected $signature = 'hs:sync';

    protected $description = 'Check database for any change and update it on HubSpot.';

    public function handle()
    {
        Log::channel('hs-sync')->info('start');

        $hs = new HubSpotController(env('HUBSPOT_API_KEY'));
        $sb_accounts = $this->get_sb_accounts();

        $this->sync_estimates($hs, $sb_accounts);
        $this->sync_work_orders($hs, $sb_accounts);

        Log::channel('hs-sync')->info('end');
    }

    private function sync_estimates($hs, $sb_accounts)
    {
        Log::channel('hs-sync')->info('sync_estimates:start');

        try {
            $estimates = Estimate::where('synced', false)->orderBy('created_at', 'asc')->get();
            $owners = $this->get_sales_representatives();
            Log::channel('hs-sync')->info('count', ['count' => $estimates->count()]);

            foreach ($estimates as $estimate) {
                try {
                    $sb = $sb_accounts[$estimate->sb_account_id];
                    $job = $sb->get_estimate($estimate->estimate_id);

                    if ($job->Status != 'Finished' && $job->Status != 'WonEstimate' && $job->Status != 'LostEstimate') {
                        $estimate->synced = true;
                        $estimate->save();
                        continue;
                    }

                    $customer = $sb->get_customer($job->Customer->Id);
                    $contact = $sb->get_contact($job->Contact->Id);
                    $location = $sb->get_location($job->Location->Id);
                    $latest_job = $this->get_latest_job($customer->Id, $sb);
                    $contact_input = $this->get_contact_input($job, $contact, $location, $customer, $latest_job, $owners);
                    $hs_contact_id = $hs->create_update_contact($job->Contact->Email, $contact_input);

                    if ($hs_contact_id) {
                        $deal = $hs->search_deal($job->EstimateNumber, $hs_contact_id);
                        $deal_name = sprintf(
                            '%s, %s, %s',
                            $job->EstimateNumber,
                            $customer->DefaultServiceLocation->AddressLine1 ?? '',
                                $latest_job['data']->Description ?? ''
                        );

                        if (empty($job->Visits)) {
                            $scheduled_at = $job->WonOrLostDate ?? '';
                        } else {
                            $scheduled_at = $job->Visits[0]->Date ?? '';
                        }

                        $deal_input = [
                            'dealname' => $deal_name,
                            'amount' => $this->get_estimate_deal_price($job),
                            'dealstage' => $this->get_deal_stage($job->Status),
                            'pipeline' => 'default',
                            'dealtype' => 'newbusiness',
                            'kilde' => $this->get_marketing_campaign($job->MarketingCampaign->Name ?? ''),
                            'hubspot_owner_id' => empty($job->SalesRepresentative->Name) ? '' : $owners[$job->SalesRepresentative->Name] ?? ''
                        ];

                        if ($scheduled_at) {
                            $deal_input['closedate'] = Carbon::parse($scheduled_at)->addDays(14)->valueOf();
                        }

                        if ($deal) {
                            $hs->update_deal($deal->dealId, $deal_input);
                        } else {
                            $hs->create_deal($hs_contact_id, $deal_input);
                        }

                        $estimate->synced = true;
                        $estimate->save();

                        Log::channel('hs-sync')->info('done', ['id' => $estimate->id, 'estimate' => $estimate->estimate_id]);
                    }
                } catch (\Exception $e) {
                    Log::channel('hs-sync')->error('sync_estimates', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getLine()
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
            $work_orders = WorkOrder::where('synced', false)->orderBy('created_at', 'asc')->get();
            $owners = $this->get_sales_representatives();
            Log::channel('hs-sync')->info('count', ['count' => $work_orders->count()]);

            foreach ($work_orders as $work_order) {
                try {
                    $sb = $sb_accounts[$work_order->sb_account_id];
                    $job = $sb->get_work_order($work_order->work_order_id);

                    if ($job->Status != 'Completed') {
                        $work_order->synced = true;
                        $work_order->save();
                        continue;
                    }

                    $customer = $sb->get_customer($job->Customer->Id);
                    $contact = $sb->get_contact($job->Contact->Id);
                    $location = $sb->get_location($job->Location->Id);
                    $latest_job = $this->get_latest_job($customer->Id, $sb);
                    $contact_input = $this->get_contact_input($job, $contact, $location, $customer, $latest_job, $owners);
                    $hs_contact_id = $hs->create_update_contact($job->Contact->Email, $contact_input);

                    if ($hs_contact_id) {
                        $work_order->synced = true;
                        $work_order->save();

                        Log::channel('hs-sync')->info('done', ['id' => $work_order->id, 'work_order' => $work_order->work_order_id]);
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
        if (preg_match('/-/mui', $work_order))
            return 'Single job';

        return 'Recurring job';
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

    private function get_sales_representatives()
    {
        $result = [];
        $owners = HubSpotOwner::all();
        foreach ($owners as $owner) {
            $name = sprintf('%s %s', $owner->first_name, $owner->last_name);
            $result[$name] = $owner->owner_id;
        }

        return $result;
    }

    private function get_contact_input($job, $contact, $location, $customer, $latest_job, $owners, $type = 'estimates')
    {
        if (empty($job->Visits)) {
            $scheduled_at = strtotime($job->WonOrLostDate) * 1000 ?? '';
        } else {
            $scheduled_at = strtotime($job->Visits[0]->Date) * 1000 ?? '';
        }

        $data = [
            'firstname' => $contact->FirstName,
            'lastname' => $contact->LastName,
            'email' => $contact->Email,
            'phone' => empty($contact->CellPhoneNumber) ? $contact->PhoneNumber : $contact->CellPhoneNumber,
            'address' => $location->AddressLine1,
            'city' => $location->City,
            'zip' => $location->PostalCode,
            'company' => empty($customer->CompanyName) ? $customer->DisplayName : $customer->CompanyName,
            'hubspot_owner_id' => empty($job->SalesRepresentative->Name) ? '' : $owners[$job->SalesRepresentative->Name] ?? '',
            'job_date_in_service_bridge' => $scheduled_at,
            'customer_type' => $customer->CustomerType ?? ''
        ];

        if ($type == 'work_order') {
            $data['status_from_sb'] = 'WO Recurring';
            $data['job_status_in_service_bridge'] = $this->get_status_of_work_order_for_hs($job->WorkOrderNumber);
            $data['lifecyclestage'] = count($job->WorkWorderLines ?? []) > 0 ? 'customer' : 'opportunity';
        } else {
            $data['job_status_in_service_bridge'] = 'Estimate';
            $data['status_from_sb'] = $this->get_status_of_estimate_for_hs($job->Status);
            $data['lifecyclestage'] = count($job->EstimateLines ?? []) > 0 ? 'customer' : 'opportunity';
        }

        if ($latest_job['type'] == 'work_order') {
            $data['notat_om_aktivitet_i_service_bridge'] = sprintf(
                '%s, %s, %s',
                    $latest_job['data']->WorkOrderNumber,
                $customer->DefaultServiceLocation->AddressLine1 ?? '',
                    $latest_job['data']->Description ?? ''
            );
        } else {
            $data['notat_om_aktivitet_i_service_bridge'] = sprintf(
                '%s, %s, %s',
                    $latest_job['data']->EstimateNumber,
                $customer->DefaultServiceLocation->AddressLine1 ?? '',
                    $latest_job['data']->Description ?? ''
            );
        }

        return $data;
    }

    private function get_latest_job($customer, $sb)
    {
        $db_estimates = Estimate::where('customer_id', $customer)->orderBy('created_at', 'desc');
        $db_work_orders = WorkOrder::where('customer_id', $customer)->orderBy('created_at', 'desc');

        if ($db_work_orders->count()) {
            if ($db_estimates->first()->created_at->valueOf() < $db_work_orders->first()->created_at->valueOf()) {
                return [
                    'type' => 'work_order',
                    'data' => $sb->get_work_order($db_work_orders->first()->work_order_id)
                ];
            }
        }

        return [
            'type' => 'estimate',
            'data' => $sb->get_estimate($db_estimates->first()->estimate_id)
        ];
    }
}
