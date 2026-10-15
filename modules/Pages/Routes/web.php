<?php

use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\PageController;

/*
| The fallback route: any URL no other route matches is looked up as a page path (/about,
| /about/team), else 404. Laravel tries fallback routes last, whatever the registration order.
| Other locales are prefixed: /ru/about (see LocalizedUrls).
*/

Route::get('/{path}', PageController::class)
    ->where('path', '.+')
    ->fallback()
    ->name('pages.show');
