<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use SevenShores\Hubspot\Factory as LegacyFactory;

class HubSpotController
{
    private $client;

    public function __construct($api_key)
    {
        $this->client = LegacyFactory::create($api_key);
    }

    public function get_contact($email)
    {
        try {
            $contacts = $this->client->contacts()->search($email);
            return $contacts->contacts[0]->vid ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('get_contact', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function create_update_contact($email, $data)
    {
        try {
            $input = [];
            foreach ($data as $key => $value) {
                $input[] = [
                    'property' => $key,
                    'value' => $value
                ];
            }

            $contact = $this->client->contacts()->createOrUpdate($email, $input);
            return $contact->vid ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('create_update_contact', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function search_deal($contact_id)
    {
        try {
            $deals = $this->client->deals()->associatedWithContact($contact_id, [
                'properties' => [
                    'dealname',
                    'amount'
                ]
            ]);

            return $deals->deals[0] ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('search_deal', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function create_deal($contact_id, $data)
    {
        try {
            $input = [];
            foreach ($data as $key => $value) {
                $input[] = [
                    'name' => $key,
                    'value' => $value
                ];
            }

            $deal = $this->client->deals()->create($input, ['associatedVids' => [$contact_id]]);
            return $deal->dealId ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('create_deal', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }

    public function update_deal($deal_id, $data)
    {
        try {
            $input = [];
            foreach ($data as $key => $value) {
                $input[] = [
                    'name' => $key,
                    'value' => $value
                ];
            }

            $deal = $this->client->deals()->update($deal_id, $input);
            return $deal->dealId ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('update_deal', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }
}
