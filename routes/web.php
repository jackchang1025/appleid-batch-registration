<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IpInfoController;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/ip-info', [IpInfoController::class, 'index']);
