<?php

namespace App\Console\Commands;

use App\Models\ServiceBridgeAccount;
use Illuminate\Console\Command;

class ServiceBridgeAccountsCommand extends Command
{
    protected $signature = 'sb:accounts';

    protected $description = 'Feed all the service bridge accounts into database.';

    public function handle()
    {
        if (env('APP_ENV') == 'local') {
            $accounts = [
                [
                    'user_id' => 'dW9vb3B1cnh1c2x1b29vb3J3dHFx0',
                    'user_pass' => 'gFYRFF8DS^Rh4a*VQdffUU2WiV7V@AkD',
                    'city' => 'Testing'
                ]
            ];
        } else {
            $accounts = [
                [
                    'user_id' => 'dW9vb292b3RyeGx1b29vb3Jxb3R30',
                    'user_pass' => 'Utvendigrenhold2021',
                    'city' => 'Sørlandet'
                ],
                [
                    'user_id' => 'dW9vb3B4eHhxb2x1b29vb3FzeHd30',
                    'user_pass' => 'APIRogaland2020',
                    'city' => 'Rogaland'
                ],
                [
                    'user_id' => 'dW9vb3FwcHFycWx1b29vb3F0b3d40',
                    'user_pass' => 'APIOslo2020',
                    'city' => 'Oslo'
                ],
                [
                    'user_id' => 'dW9vb3FweHZwdWx1b29vb3F1dm900',
                    'user_pass' => 'APIDrammen2020',
                    'city' => 'Drammen'
                ],
                [
                    'user_id' => 'dW9vb3FydnN4cWx1b29vb3JwdXRz0',
                    'user_pass' => 'TrondheimAPI2021',
                    'city' => 'Trondheim'
                ],
                [
                    'user_id' => 'dW9vb3FzdXJ3b2x1b29vb3JydXZz0',
                    'user_pass' => 'BergenAPI2021',
                    'city' => 'Bergen'
                ],
                [
                    'user_id' => 'dW9vb3F0cHFxd2x1b29vb3J0d3h10',
                    'user_pass' => '@InnlandetAPI2022',
                    'city' => 'Innlandet'
                ],
                [
                    'user_id' => 'dW9vb3F0cHdyc2x1b29vb3J2cW940',
                    'user_pass' => '@OstfoldAPI2022',
                    'city' => 'Ostfold'
                ]
            ];
        }

        ServiceBridgeAccount::all()->each->delete();
        ServiceBridgeAccount::insert($accounts);

        return Command::SUCCESS;
    }
}
