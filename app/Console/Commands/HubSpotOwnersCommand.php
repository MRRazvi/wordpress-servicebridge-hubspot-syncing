<?php

namespace App\Console\Commands;

use App\Models\HubSpotOwner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SevenShores\Hubspot\Factory as LegacyFactory;

class HubSpotOwnersCommand extends Command
{
    protected $signature = 'hs:owners';

    protected $description = 'Fetch all the owners from HubSpot and store into database.';

    public function handle()
    {
        Log::channel('hs-owners')->info('start');

        try {
            $client = LegacyFactory::create(env('HUBSPOT_API_KEY'));
            $owners = $client->owners()->all()->data;
            HubSpotOwner::truncate();

            Log::channel('hs-owners')->info('count', ['count' => count($owners)]);

            foreach ($owners as $owner) {
                HubSpotOwner::create([
                    'owner_id' => $owner->ownerId,
                    'first_name' => $owner->firstName,
                    'last_name' => $owner->lastName,
                    'email' => $owner->email
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('hs-owners')->error('handle', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('hs-owners')->info('end');
    }
}
