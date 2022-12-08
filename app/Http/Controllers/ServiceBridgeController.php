<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\WorkOrder;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class ServiceBridgeController
{
    public $client;
    public $base_url;
    public $session_key;

    public function __construct()
    {
        $this->client = new Client();
        $this->base_url = env('SB_API_BASE_URL');
    }

    public function login()
    {
        $response = $this->client->request(
            'POST',
            sprintf('%s/Login', $this->base_url),
            [
                'json' => [
                    'UserId' => env('SB_API_USER_ID'),
                    'Password' => env('SB_API_USER_PASS')
                ]
            ]
        );

        $response = json_decode($response->getBody()->getContents());
        $this->session_key = $response->Data;
    }

    public function get_estimates()
    {
        $estimates = [];

        for ($i = 1; $i <= $this->get_estimates_count() / 500; $i++) {
            $estimates[] = $this->client->requestAsync(
                'GET',
                sprintf('%s/Estimates', $this->base_url),
                [
                    'query' => [
                        'sessionKey' => $this->session_key,
                        'page' => $i,
                        'pageSize' => (env('APP_ENV') == 'local') ? 5 : 500,
                        'includeInactiveCustomers' => true,
                        'includeInventoryInfo' => true
                    ]
                ]
            )->then(function ($response) use ($i) {
                $response = json_decode($response->getBody()->getContents());
                return $response->Data;
            });
        }

        $estimates = Promise\Utils::settle(
            Promise\Utils::unwrap($estimates),
        )->wait();

        foreach ($estimates as $_estimate) {
            foreach ($_estimate['value'] as $estimate) {
                Estimate::updateOrCreate([
                    'estimate_id' => $estimate->Id,
                    'status' => $estimate->Status,
                    'blob' => json_encode($estimate)
                ]);
            }
        }

        return $estimates;
    }

    public function get_estimates_count()
    {
        if (env('APP_ENV') == 'local')
            return 500;

        $response = $this->client->request(
            'GET',
            sprintf('%s/Estimates', $this->base_url),
            [
                'query' => [
                    'sessionKey' => $this->session_key,
                    'page' => 1,
                    'pageSize' => 1
                ]
            ]
        );

        $response = json_decode($response->getBody()->getContents());
        return $response->TotalCount;
    }

    public function get_work_orders()
    {
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
                        'includeInactiveCustomers' => true
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

        foreach ($work_orders as $_work_order) {
            foreach ($_work_order['value'] as $work_order) {
                WorkOrder::updateOrCreate([
                    'work_order_id' => $work_order->Id,
                    'status' => $work_order->Status,
                    'blob' => json_encode($work_order)
                ]);
            }
        }

        return $work_orders;
    }

    public function get_work_orders_count()
    {
        if (env('APP_ENV') == 'local')
            return 500;

        $response = $this->client->request(
            'GET',
            sprintf('%s/WorkOrders', $this->base_url),
            [
                'query' => [
                    'sessionKey' => $this->session_key,
                    'page' => 1,
                    'pageSize' => 1
                ]
            ]
        );

        $response = json_decode($response->getBody()->getContents());
        return $response->TotalCount;
    }
}
