<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('themes.default.home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
|
| The dashboard. Module admin pages are registered by each module under
| /{admin_prefix}/{slug}; the login pages by Fortify under /{admin_prefix}.
|
*/

Route::prefix(config('cms.admin_prefix'))
    ->middleware(config('cms.admin_middleware'))
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
