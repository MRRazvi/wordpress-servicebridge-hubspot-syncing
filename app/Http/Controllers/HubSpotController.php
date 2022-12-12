<?php

namespace App\Http\Controllers;

use \HubSpot\Factory;
use \HubSpot\Client\Crm\Contacts\Model\Filter as ContactFilter;
use \HubSpot\Client\Crm\Deals\Model\Filter as DealFilter;
use \HubSpot\Client\Crm\Contacts\Model\FilterGroup as ContactFilterGroup;
use \HubSpot\Client\Crm\Deals\Model\FilterGroup as DealFilterGroup;
use \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest as ContactPublicObjectSearchRequest;
use \HubSpot\Client\Crm\Deals\Model\PublicObjectSearchRequest as DealPublicObjectSearchRequest;
use \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactInput;
use \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput as DealInput;

class HubSpotController
{
    private $client;

    public function __construct($api_key)
    {
        $this->client = Factory::createWithDeveloperApiKey($api_key);
    }

    public function get_contact($email)
    {
        $filter = new ContactFilter();
        $filter->setOperator('EQ')->setPropertyName('email')->setValue($email);
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
        $deal = $this->client->crm()->deals()->basicApi()->getById($id);

        return $deal;
    }

    public function create_deal($data)
    {
        $dealInput = new DealInput();
        $dealInput->setProperties($data);
        return $this->client->crm()->deals()->basicApi()->create($dealInput);
    }
}
