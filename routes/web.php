<?php

use App\Http\Controllers\HubSpotController;
use App\Models\Estimate;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/test', function () {
    $hs = new HubSpotController(env('HUBSPOT_ACCESS_TOKEN'));

    dd($hs->get_contact('robin.tonnesen@newsec.no'));
});

Route::get('/setup', function () {
    Artisan::call('migrate:fresh --force');
    Artisan::call('sb:accounts');
    Artisan::call('hs:owners');
});

Route::get('/sb-sync', function () {
    Artisan::call('sb:sync');
});

Route::get('/hs-sync', function () {
    Artisan::call('hs:sync');
});

Route::get('/scheduler', function () {
    Artisan::call('schedule:run');
});

Route::get('/stats', function () {
    return [
        'estimates' => [
            'done' => Estimate::where('synced', true)->count(),
            'undone' => Estimate::where('synced', false)->count()
        ],
        'work_orders' => [
            'done' => WorkOrder::where('synced', true)->count(),
            'undone' => WorkOrder::where('synced', false)->count()
        ]
    ];
});
