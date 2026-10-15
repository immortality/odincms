<?php

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\Admin\ContactController;

/*
| Loaded under /{admin_prefix}/contacts with the names "admin.contacts.*" and the middleware in
| config('cms.admin_middleware') (web, auth, verified, admin). Each action is authorized by ContactPolicy.
*/

Route::controller(ContactController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{contact}/edit', 'edit')->name('edit');
    Route::put('/{contact}', 'update')->name('update');
    Route::delete('/{contact}', 'destroy')->name('destroy');
});
