<?php

namespace App\Console\Commands;

use App\Models\ServiceBridgeAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ServiceBridgeAccountsCommand extends Command
{
    protected $signature = 'sb:accounts';

    protected $description = 'Feed all the service bridge accounts into database.';

    public function handle()
    {
        Log::channel('sb-accounts')->info('start');

        try {
            if (env('APP_ENV') == 'local') {
                $accounts = [
                    [
                        'user_id' => 'X',
                        'user_pass' => 'X',
                        'city' => 'X'
                    ]
                ];
            } else {
                $accounts = [
                    [
                        'user_id' => 'X',
                        'user_pass' => 'X',
                        'city' => 'X'
                    ]
                ];
            }

            ServiceBridgeAccount::all()->each->delete();
            ServiceBridgeAccount::insert($accounts);
        } catch (\Exception $e) {
            Log::channel('sb-accounts')->error('handle', [
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'message' => $e->getMessage()
            ]);
        }

        Log::channel('sb-accounts')->info('end');
    }
}
