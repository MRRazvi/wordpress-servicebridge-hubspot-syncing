<?php

namespace App\Http\Controllers;

use \HubSpot\Factory;
use Illuminate\Support\Facades\Http;
use SevenShores\Hubspot\Factory as LegacyFactory;
use \HubSpot\Client\Crm\Contacts\Model\Filter as ContactFilter;
use \HubSpot\Client\Crm\Contacts\Model\FilterGroup as ContactFilterGroup;
use \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactInput;
use HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput as DealSimplePublicObjectInput;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactSimplePublicObjectInput;
use \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest as ContactPublicObjectSearchRequest;

class HubSpotController
{
    private $client;

    private $base_url;

    private $api_key;

    public function __construct($api_key)
    {
        $this->client = Factory::createWithDeveloperApiKey($api_key);
        $this->base_url = 'https://api.hubapi.com/deals/v1';
        $this->api_key = $api_key;
    }

    public function get_contact($email)
    {
        $filter = new ContactFilter();
        $filter
            ->setOperator('EQ')
            ->setPropertyName('email')
            ->setValue($email);
        $filterGroup = new ContactFilterGroup();
        $filterGroup->setFilters([$filter]);
        $searchRequest = new ContactPublicObjectSearchRequest();
        $searchRequest->setFilterGroups([$filterGroup]);
        $searchRequest->setProperties([
            'firstname',
            'lastname',
            'email',
            'phone',
            'address',
            'zip',
            'city',
            'lifecyclestage',
            'status_from_sb',
            'notat_om_aktivitet_i_service_bridge'
        ]);
        $contactsPage = $this->client->crm()->contacts()->searchApi()->doSearch($searchRequest);

        if ($contactsPage['total'] < 1) {
            return false;
        }

        return $contactsPage['results'][0];
    }

    public function create_contact($data)
    {
        $contactInput = new ContactInput();
        $contactInput->setProperties($data);

        return $this->client->crm()->contacts()->basicApi()->create($contactInput);
    }

    public function update_contact($contact_id, $data)
    {
        $data = new ContactSimplePublicObjectInput([
            'properties' => $data
        ]);

        return $this->client->crm()->contacts()->basicApi()->update($contact_id, $data);
    }

    public function search_deal($contact_id)
    {
        $hs = LegacyFactory::create($this->api_key);
        $deals = $hs->deals()->associatedWithContact($contact_id, [
            'properties' => [
                'dealname',
                'amount'
            ]
        ]);

        if ($deals->deals) {
            return $deals->deals[0];
        }

        return [];
    }

    public function get_deal($id)
    {
        return $this->client->crm()->deals()->basicApi()->getById($id);
    }

    public function create_deal($user_id, $data)
    {
        $response = Http::post(
            sprintf('%s/deal?hapikey=%s', $this->base_url, $this->api_key),
            [
                'associations' => [
                    'associatedVids' => $user_id
                ],
                'properties' => [
                    [
                        'name' => 'dealname',
                        'value' => $data['dealname']
                    ],
                    [
                        'name' => 'amount',
                        'value' => $data['amount']
                    ],
                    [
                        'name' => 'pipeline',
                        'value' => $data['pipeline']
                    ],
                    [
                        'name' => 'dealtype',
                        'value' => $data['dealtype']
                    ],
                    [
                        'name' => 'dealstage',
                        'value' => $data['dealstage']
                    ],
                    [
                        'name' => 'closedate',
                        'value' => $data['closedate']
                    ]
                ]
            ]
        );

        return $response->json();
    }

    public function update_deal($deal_id, $data)
    {
        $data = new DealSimplePublicObjectInput([
            'properties' => $data
        ]);

        return $this->client->crm()->deals()->basicApi()->update($deal_id, $data);
    }
}
