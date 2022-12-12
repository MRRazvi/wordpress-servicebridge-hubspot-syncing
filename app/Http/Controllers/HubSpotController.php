<?php

namespace App\Http\Controllers;

use \HubSpot\Factory;
use \HubSpot\Client\Crm\Contacts\Model\Filter as ContactFilter;
use \HubSpot\Client\Crm\Contacts\Model\FilterGroup as ContactFilterGroup;
use \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest as ContactPublicObjectSearchRequest;
use \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactInput;
use Illuminate\Support\Facades\Http;

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
        $filter->setOperator('EQ')->setPropertyName('email')->setValue($email);
        $filter->setOperator('CONTAINS_TOKEN')->setPropertyName('hs_additional_emails')->setValue($email);
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
}
