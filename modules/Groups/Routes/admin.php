<?php

use Illuminate\Support\Facades\Route;
use Modules\Groups\Http\Controllers\Admin\GroupController;

/*
| Loaded under /{admin_prefix}/groups with the names "admin.groups.*" and the middleware in
| config('cms.admin_middleware'). Each action is authorized by GroupPolicy.
*/

Route::controller(GroupController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{group}/edit', 'edit')->name('edit');
    Route::put('/{group}', 'update')->name('update');
    Route::delete('/{group}', 'destroy')->name('destroy');
});
