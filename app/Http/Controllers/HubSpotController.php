<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

class HubSpotController
{
    private $client;

    public function __construct($access_token)
    {
        $this->client = \HubSpot\Factory::createWithAccessToken($access_token);
    }

    public function get_contact($email)
    {
        try {
            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($email);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);
            $contactsPage = $this->client->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if ($contactsPage['total'] < 1) {
                return false;
            }

            return $contactsPage['results'][0];
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
            $contact = $this->get_contact($email);

            $contact_data = new \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput();
            $contact_data->setProperties($data);

            if ($contact) {
                // update contact
                $contact_id = $contact['id'];
                $updated_contact = $this->client->crm()->contacts()->basicApi()->update($contact_id, $contact_data);
                return $updated_contact['id'] ?? false;
            } else {
                // create contact
                $new_contact = $this->client->crm()->contacts()->basicApi()->create($contact_data);
                return $new_contact['id'] ?? false;
            }
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('create_update_contact', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
    }

    public function search_deal($estimate_number, $contact_id, $tries)
    {
        try {
            $filter = new \HubSpot\Client\Crm\Deals\Model\Filter();
            $filter
                ->setOperator('CONTAINS_TOKEN')
                ->setPropertyName('dealname')
                ->setValue($estimate_number);

            $filterGroup = new \HubSpot\Client\Crm\Deals\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Deals\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);

            $dealsPage = $this->client->crm()->deals()->searchApi()->doSearch($searchRequest);

            if ($dealsPage['total'] < 1) {
                return false;
            }

            return $dealsPage['results'][0];
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
            // create deal
            $deal_data = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();
            $deal_data->setProperties($data);

            $new_deal = $this->client->crm()->deals()->basicApi()->create($deal_data);
            if ($new_deal['id']) {
                // associate contact
                $associationRequest = new \HubSpot\Client\Crm\Associations\Model\BatchInputPublicAssociation();
                $associationRequest->setInputs([
                    [
                        "from" => [
                            "id" => $contact_id,
                            "type" => "contact"
                        ],
                        "to" => [
                            "id" => $new_deal['id'],
                            "type" => "deal"
                        ],
                        "type" => "contact_to_deal"
                    ]
                ]);

                $this->client->crm()->associations()->batchApi()->create('contacts', 'deals', $associationRequest);
            }

            return $new_deal['id'] ?? false;
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
            $deal_data = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();
            $deal_data->setProperties($data);

            $updated_deal = $this->client->crm()->deals()->basicApi()->update($deal_id, $deal_data);
            return $updated_deal['id'] ?? false;
        } catch (\Exception $e) {
            Log::channel('hs-sync')->error('update_deal', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }
    }
}