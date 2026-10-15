<?php

use Illuminate\Support\Facades\Route;
use Modules\Modules\Http\Controllers\Admin\ModuleController;

/*
| Loaded under /{admin_prefix}/modules with the names "admin.modules.*" and the middleware in
| config('cms.admin_middleware'). modules.view to look, modules.manage to change,
| modules.uninstall to uninstall.
*/

Route::get('/', [ModuleController::class, 'index'])->name('index');
Route::post('/sync', [ModuleController::class, 'sync'])->name('sync');
Route::post('/{module:slug}/{action}', [ModuleController::class, 'action'])
    ->whereIn('action', ['install', 'enable', 'disable', 'upgrade', 'uninstall'])
    ->name('action');
