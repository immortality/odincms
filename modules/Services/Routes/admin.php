<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\Admin\ServiceController;

/*
| Loaded under /{admin_prefix}/services with the names "admin.services.*" and the middleware in
| config('cms.admin_middleware') (web, auth, verified, admin). Each action is authorized by ServicePolicy.
*/

Route::controller(ServiceController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{item}/edit', 'edit')->name('edit');
    Route::put('/{item}', 'update')->name('update');
    Route::delete('/{item}', 'destroy')->name('destroy');
});
