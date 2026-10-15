<?php

use Illuminate\Support\Facades\Route;
use Modules\Widgets\Http\Controllers\Admin\AreaController;
use Modules\Widgets\Http\Controllers\Admin\WidgetController;

/*
| Loaded under /{admin_prefix}/widgets with the names "admin.widgets.*" and the middleware in
| config('cms.admin_middleware'). Permissions: widgets.view|create|edit|delete.
*/

Route::controller(AreaController::class)->prefix('areas')->name('areas.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{area}/edit', 'edit')->name('edit');
    Route::put('/{area}', 'update')->name('update');
    Route::delete('/{area}', 'destroy')->name('destroy');
});

Route::controller(WidgetController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/reorder', 'reorder')->name('reorder');
    Route::get('/{instance}/edit', 'edit')->name('edit');
    Route::put('/{instance}', 'update')->name('update');
    Route::delete('/{instance}', 'destroy')->name('destroy');
});
