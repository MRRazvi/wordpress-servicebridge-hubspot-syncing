<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/sb-sync', function () {
    Artisan::call('sb:sync');
});

Route::get('/hs-sync', function () {
    Artisan::call('hs:sync');
});

Route::get('/scheduler', function () {
    Artisan::call('schedule:run');
});
