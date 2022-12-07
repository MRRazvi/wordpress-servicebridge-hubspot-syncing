<?php

namespace App\Services;

use Symfony\Component\HttpClient\HttpClient;

class ServiceBridge
{
    public $base_url = 'https://cloud.servicebridge.com/api/v4';
    public $client;

    public function __construct($api_user_id)
    {
        $this->client = HttpClient::create();
    }

    public function login()
    {
    }
}
