<?php

use App\Http\Controllers\ServiceBridgeController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Artisan::call('sb:database');
    Artisan::call('hs:sync');
});

Route::get('/sb', function () {
    $sb = new ServiceBridgeController('dW9vb3F0cHdyc2x1b29vb3J2cW940', '@OstfoldAPI2022');
    $sb->login();
    dd($sb->get_contact(6004865114));
});
