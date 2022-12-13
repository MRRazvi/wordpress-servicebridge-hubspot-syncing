<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
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

    public function __construct($user_id, $user_pass)
    {
        $this->client = new Client();
        $this->base_url = env('SB_API_BASE_URL');
        $this->user_id = $user_id;
        $this->user_pass = $user_pass;
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
            Log::channel('sb-client')->error('login', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }

    public function get_estimates()
    {
        try {
            $estimates = [];
            $statues = ['Finished', 'WonEstimate', 'LostEstimate'];

            foreach ($statues as $status) {
                for ($i = 1; $i <= $this->get_estimates_count($status) / 500; $i++) {
                    dump(sprintf("starting: %s - %s", $i, $status));

                    $estimates[] = $this->client->requestAsync(
                        'GET',
                        sprintf('%s/Estimates', $this->base_url),
                        [
                            'query' => [
                                'sessionKey' => $this->session_key,
                                'page' => $i,
                                'pageSize' => (env('APP_ENV') == 'local') ? 5 : 500,
                                'includeInactiveCustomers' => true,
                                'includeInventoryInfo' => true,
                                'statusFilter' => $status
                            ]
                        ]
                    )->then(function ($response) use ($i, $status) {
                        dump(sprintf("reaching: %s - %s", $i, $status));

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
                                    'sb_account_id' => 1,
                                    'status' => $estimate->Status,
                                    'version' => $estimate->Metadata->Version,
                                    'synced' => false,
                                    'created_at' => $estimate->Metadata->CreatedOn,
                                    'updated_at' => $estimate->Metadata->UpdatedOn
                                ]);
                            }
                        }

                        return $response->TotalCount;
                    });
                }
            }

            $estimates = Promise\Utils::settle(
                Promise\Utils::unwrap($estimates),
            )->wait();

            dd($estimates);

            return $estimates;
        } catch (\Exception $e) {
            Log::channel('sb-client')->error('get_estimates', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
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
            Log::channel('sb-client')->error('get_estimates_count', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }

    public function get_work_orders()
    {
        try {
            $work_orders = [];

            for ($i = 1; $i <= $this->get_work_orders_count() / 500; $i++) {
                $work_orders[] = $this->client->requestAsync(
                    'GET',
                    sprintf('%s/WorkOrders', $this->base_url),
                    [
                        'query' => [
                            'sessionKey' => $this->session_key,
                            'page' => $i,
                            'pageSize' => (env('APP_ENV') == 'local') ? 5 : 500,
                            'includeInactiveCustomers' => true,
                            'statusFilter' => 'Completed'
                        ]
                    ]
                )->then(function ($response) use ($i) {
                    $response = json_decode($response->getBody()->getContents());
                    return $response->Data;
                });
            }

            $work_orders = Promise\Utils::settle(
                Promise\Utils::unwrap($work_orders),
            )->wait();

            return $work_orders;
        } catch (\Exception $e) {
            Log::channel('sb-client')->error('get_work_orders', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
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
            Log::channel('sb-client')->error('get_work_orders_count', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
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
            Log::channel('sb-client')->error('get_estimate', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
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
            Log::channel('sb-client')->error('get_work_order', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }

    public function get_contact($id)
    {
        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/Contacts/%s', $this->base_url, $id),
                [
                    'query' => [
                        'sessionKey' => $this->session_key
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->Data;
        } catch (\Exception $e) {
            Log::channel('sb-client')->error('get_contact', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
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
            Log::channel('sb-client')->error('get_customer', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }

    public function get_location($id)
    {
        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/Locations/%s', $this->base_url, $id),
                [
                    'query' => [
                        'sessionKey' => $this->session_key
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents());
            return $response->Data;
        } catch (\Exception $e) {
            Log::channel('sb-client')->error('get_location', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            ]);
        }
    }
}
