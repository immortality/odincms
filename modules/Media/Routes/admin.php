<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\Http\Controllers\Admin\FolderController;
use Modules\Media\Http\Controllers\Admin\MediaController;

/*
| Loaded under /{admin_prefix}/media with the names "admin.media.*" and the middleware in
| config('cms.admin_middleware'). Each action checks a media.* permission.
*/

Route::controller(MediaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/files', 'store')->name('files.store');
    Route::post('/files/bulk', 'bulk')->name('files.bulk');
    Route::get('/files/{file}/download', 'download')->name('files.download');
    Route::patch('/files/{file}', 'update')->name('files.update');
    Route::delete('/files/{file}', 'destroy')->name('files.destroy');
});

Route::controller(FolderController::class)->group(function () {
    Route::post('/folders', 'store')->name('folders.store');
    Route::patch('/folders/{folder}', 'update')->name('folders.update');
    Route::delete('/folders/{folder}', 'destroy')->name('folders.destroy');
});
