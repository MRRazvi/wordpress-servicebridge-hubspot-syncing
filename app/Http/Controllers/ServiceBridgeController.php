<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

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
    }

    public function get_estimates()
    {
        $estimates = [];
        $statues = ['Finished', 'WonEstimate', 'LostEstimate'];

        foreach ($statues as $status) {
            for ($i = 1; $i <= $this->get_estimates_count($status) / 500; $i++) {
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
                )->then(function ($response) use ($i) {
                    $response = json_decode($response->getBody()->getContents());
                    return $response->Data;
                });
            }
        }

        $estimates = Promise\Utils::settle(
            Promise\Utils::unwrap($estimates),
        )->wait();

        dd($estimates);

        return $estimates;
    }

    public function get_estimates_count($status)
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
                    'pageSize' => 1,
                    'statusFilter' => $status
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

    public function get_estimate($id)
    {
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
    }

    public function get_work_order($id)
    {
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
    }

    public function get_contact($id)
    {
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
    }

    public function get_customer($id)
    {
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
    }

    public function get_location($id)
    {
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
    }
}
