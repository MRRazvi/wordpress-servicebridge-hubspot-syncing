<?php

namespace App\Http\Controllers;

use \HubSpot\Factory;
use \HubSpot\Client\Crm\Contacts\Model\Filter;
use \HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput;
use \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;

class HubSpotController
{
    private $client;

    public function __construct($api_key)
    {
        $this->client = Factory::createWithDeveloperApiKey($api_key);
    }

    public function get_contact($email)
    {
        $filter = new Filter();
        $filter->setOperator('EQ')->setPropertyName('email')->setValue($email);
        $filterGroup = new FilterGroup();
        $filterGroup->setFilters([$filter]);
        $searchRequest = new PublicObjectSearchRequest();
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
        $contactInput = new SimplePublicObjectInput();
        $contactInput->setProperties($data);
        return $this->client->crm()->contacts()->basicApi()->create($contactInput);
    }
}
