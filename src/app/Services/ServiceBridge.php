<?php

namespace App\Services;

use App\Utils\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class ServiceBridge
{
    public $log;
    public $client;
    public $base_url = 'https://cloud.servicebridge.com/api/v4';
    public $session_key;

    public function __construct()
    {
        $this->client = new Client();
        $this->log = new Log();
    }

    public function login()
    {
        try {
            $response = $this->client->request(
                'POST',
                sprintf('%s/Login', $this->base_url),
                [
                    'json' => [
                        'UserId' => $_ENV['SB_API_USER_ID'],
                        'Password' => $_ENV['SB_API_USER_PASS']
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents(), true);
            $this->session_key = $response['Data'];
        } catch (\Exception $e) {
            $this->log->error('sb:login:' . $e->getMessage());
        }
    }

    public function estimates()
    {
        try {
            $estimates = [];

            $response = $this->client->request(
                'GET',
                sprintf('%s/Estimates', $this->base_url),
                [
                    'query' => [
                        'sessionKey' => $this->session_key,
                        'page' => 1,
                        'pageSize' => 1,
                        'includeInactiveCustomers' => true,
                        'includeInventoryInfo' => true
                    ]
                ]
            );

            $response = json_decode($response->getBody()->getContents(), true);
            $total_count = $response['TotalCount'];

            for ($i = 1; $i <= $total_count / 500; $i++) {
                $estimates[] = $this->client->requestAsync(
                    'GET',
                    sprintf('%s/Estimates', $this->base_url),
                    [
                        'query' => [
                            'sessionKey' => $this->session_key,
                            'page' => $i,
                            'pageSize' => 500,
                            'includeInactiveCustomers' => true,
                            'includeInventoryInfo' => true
                        ]
                    ]
                )->then(function ($response) use ($i) {
                    $response = json_decode($response->getBody()->getContents(), true);
                    return $response['Data'];
                });
            }

            $estimates = Promise\Utils::settle(
                Promise\Utils::unwrap($estimates),
            )->wait();

            return $estimates;
        } catch (\Exception $e) {
            $this->log->error('sb:estimates:' . $e->getMessage());
        }
    }
}