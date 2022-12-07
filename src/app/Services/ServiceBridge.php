<?php

namespace App\Services;

use App\Utils\Log;
use Symfony\Component\HttpClient\HttpClient;

class ServiceBridge
{
    public $log;
    public $client;
    public $base_url = 'https://cloud.servicebridge.com/api/v4';
    public $session_key;

    public function __construct()
    {
        $this->client = HttpClient::create();
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

            $response = $response->toArray();
            $this->session_key = $response['Data'];
        } catch (\Exception $e) {
            $this->log->error('sb:login:' . $e->getMessage());
        }
    }
}