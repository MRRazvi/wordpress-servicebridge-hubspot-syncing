<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\ServiceBridgeAccount;
use App\Models\WorkOrder;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Log;

class ServiceBridgeController
{
    public $client;

    public $base_url;

    public $user_id;

    public $user_pass;

    public $session_key;

    public $sb_account_id;

    public function __construct($user_id, $user_pass)
    {
        $this->client = new Client();
        $this->base_url = env('SB_API_BASE_URL');
        $this->user_id = $user_id;
        $this->user_pass = $user_pass;

        $sb_account = ServiceBridgeAccount::where('user_id', $user_id)->first();
        $this->sb_account_id = $sb_account->id;
    }

    public function login()
    {
        try {
            $response = $this->client->request(
                'POST',
                sprintf('%s/Login', $this->base_url),
                [
                    'json' => [
                        'UserId' => $this->user_id,
                        'Password' => $this->user_pass
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            $this->session_key = $response->Data;

            return $response->Data;
        } catch (\Exception $e) {
            Log::error('sb:login', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_estimates()
    {
        Log::channel('sb-sync')->info('get_estimates:start');

        try {
            $estimates = [];
            $statues = ['Finished', 'WonEstimate', 'LostEstimate'];

            foreach ($statues as $status) {
                $total_count = $this->get_estimates_count($status);
                Log::channel('sb-sync')->info('count', ['status' => $status, 'count' => $total_count]);

                for ($i = 1; $i <= $total_count / 500 + 1; $i++) {
                    Log::channel('sb-sync')->info('request:init', ['index' => $i, 'status' => $status]);

                    $query = [
                        'sessionKey' => $this->session_key,
                        'page' => $i,
                        'pageSize' => (env('APP_ENV') == 'local') ? 5 : 500,
                        'includeInactiveCustomers' => true,
                        'includeInventoryInfo' => true,
                        'statusFilter' => $status
                    ];

                    $estimates[] = $this->client->requestAsync(
                        'GET',
                        sprintf('%s/Estimates', $this->base_url),
                        ['query' => $query]
                    )->then(function ($response) use ($i, $status) {
                        $response = json_decode($response->getBody()->getContents());
                        $estimates = $response->Data;

                        foreach ($estimates as $estimate) {
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
                                            'created_at' => $estimate->Metadata->CreatedOn,
                                            'updated_at' => $estimate->Metadata->UpdatedOn
                                        ]);
                                }
                            } else {
                                Estimate::create([
                                    'estimate_id' => $estimate->Id,
                                    'sb_account_id' => $this->sb_account_id,
                                    'status' => $estimate->Status,
                                    'version' => $estimate->Metadata->Version,
                                    'synced' => false,
                                    'created_at' => $estimate->Metadata->CreatedOn,
                                    'updated_at' => $estimate->Metadata->UpdatedOn
                                ]);
                            }
                        }

                        Log::channel('sb-sync')->info('request:done', ['index' => $i, 'status' => $status]);
                    });
                }
            }

            $estimates = Promise\Utils::settle(
                Promise\Utils::unwrap($estimates),
            )->wait();
        } catch (\Exception $e) {
            Log::channel('sb-sync')->error('get_estimates', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('sb-sync')->info('get_estimates:end');
    }

    public function get_estimates_count($status)
    {
        try {
            if (env('APP_ENV') == 'local')
                return 500;

            $response = $this->client->request(
                'GET',
                sprintf('%s/Estimates', $this->base_url),
                [
                    'query' => [
                        'sessionKey' => $this->session_key,
                        'page' => 1,
                        'pageSize' => 1,
                        'statusFilter' => $status
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->TotalCount;
        } catch (\Exception $e) {
            Log::channel('sb-sync')->error('get_estimates_count', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_work_orders()
    {
        Log::channel('sb-sync')->info('get_work_orders:start');

        try {
            $work_orders = [];
            $total_count = $this->get_work_orders_count();
            Log::channel('sb-sync')->info('count', ['count' => $total_count]);

            for ($i = 1; $i <= $total_count / 500 + 1; $i++) {
                Log::channel('sb-sync')->info('request:init', ['index' => $i]);

                $query = [
                    'sessionKey' => $this->session_key,
                    'page' => $i,
                    'pageSize' => (env('APP_ENV') == 'local') ? 5 : 500,
                    'includeInactiveCustomers' => true,
                    'statusFilter' => 'Completed'
                ];

                $work_orders[] = $this->client->requestAsync(
                    'GET',
                    sprintf('%s/WorkOrders', $this->base_url),
                    ['query' => $query]
                )->then(function ($response) use ($i) {
                    $response = json_decode($response->getBody()->getContents());
                    $work_orders = $response->Data;

                    foreach ($work_orders as $work_order) {
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
                                        'created_at' => $work_order->Metadata->CreatedOn,
                                        'updated_at' => $work_order->Metadata->UpdatedOn
                                    ]);
                            }
                        } else {
                            WorkOrder::create([
                                'work_order_id' => $work_order->Id,
                                'sb_account_id' => $this->sb_account_id,
                                'status' => $work_order->Status,
                                'version' => $work_order->Metadata->Version,
                                'synced' => false,
                                'created_at' => $work_order->Metadata->CreatedOn,
                                'updated_at' => $work_order->Metadata->UpdatedOn
                            ]);
                        }
                    }

                    Log::channel('sb-sync')->info('request:done', ['index' => $i]);
                });
            }

            $work_orders = Promise\Utils::settle(
                Promise\Utils::unwrap($work_orders),
            )->wait();
        } catch (\Exception $e) {
            Log::channel('sb-sync')->error('get_work_orders', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('sb-sync')->info('get_work_orders:end');
    }

    public function get_work_orders_count()
    {
        try {
            if (env('APP_ENV') == 'local')
                return 500;

            $response = $this->client->request(
                'GET',
                sprintf('%s/WorkOrders', $this->base_url),
                [
                    'query' => [
                        'sessionKey' => $this->session_key,
                        'page' => 1,
                        'pageSize' => 1,
                        'statusFilter' => 'Completed'
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->TotalCount;
        } catch (\Exception $e) {
            Log::channel('sb-sync')->error('get_work_orders_count', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_estimate($id)
    {
        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/Estimates/%s', $this->base_url, $id),
                [
                    'query' => [
                        'sessionKey' => $this->session_key
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->Data;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('get_estimate', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_work_order($id)
    {
        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/WorkOrders/%s', $this->base_url, $id),
                [
                    'query' => [
                        'sessionKey' => $this->session_key
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->Data;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('get_work_order', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_customer($id)
    {
        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/Customers/%s', $this->base_url, $id),
                [
                    'query' => [
                        'sessionKey' => $this->session_key,
                        'includeCustomFields' => true
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->Data;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('get_customer', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }
}
