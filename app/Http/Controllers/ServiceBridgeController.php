<?php

namespace App\Http\Controllers;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

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
        $response = $this->client->post(
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
        dump($this->build_estimate_url(1));

        $requests = function ($total) {
            for ($i = 1; $i <= $total; $i++) {
                yield new Request('GET', $this->build_estimate_url($i));
            }
        };
    }

    public function get_estimate_count()
    {

    }

    private function build_estimate_url($page)
    {
        return sprintf(
            '%s/Estimates?%s',
            $this->base_url,
            http_build_query([
                'sessionKey' => $this->session_key,
                'page' => $page,
                'pageSize' => 500,
                'includeInventoryInfo' => true,
                'includeInactiveCustomers' => true
            ])
        );
    }
}
