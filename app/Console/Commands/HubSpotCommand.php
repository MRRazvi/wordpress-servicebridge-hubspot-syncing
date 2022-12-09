<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use Illuminate\Console\Command;

class HubSpotCommand extends Command
{
    protected $signature = 'hs:sync';

    protected $description = 'Check database for any change and update it on HubSpot.';

    public function handle()
    {
        $hubspot = \HubSpot\Factory::createWithDeveloperApiKey(env('HUBSPOT_API_KEY'));

        $estimates = Estimate::where('synced', false)->get();
        foreach ($estimates as $estimate) {

        }
    }
}
