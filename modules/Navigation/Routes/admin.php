<?php

use Illuminate\Support\Facades\Route;
use Modules\Navigation\Http\Controllers\Admin\GroupController;
use Modules\Navigation\Http\Controllers\Admin\LinkableSearchController;
use Modules\Navigation\Http\Controllers\Admin\LinkController;

/*
| Loaded under /{admin_prefix}/navigation with the names "admin.navigation.*" and the middleware in
| config('cms.admin_middleware'). Permissions: navigation.view|create|edit|delete.
*/

Route::get('/linkables', LinkableSearchController::class)->name('linkables');

Route::controller(GroupController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{group}/edit', 'edit')->name('edit');
    Route::put('/{group}', 'update')->name('update');
    Route::delete('/{group}', 'destroy')->name('destroy');
});

Route::controller(LinkController::class)->prefix('/{group}/links')->name('links.')->scopeBindings()->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/reorder', 'reorder')->name('reorder');
    Route::get('/{link}/edit', 'edit')->name('edit');
    Route::put('/{link}', 'update')->name('update');
    Route::delete('/{link}', 'destroy')->name('destroy');
});
