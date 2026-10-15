<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\BlogController;

/*
| The public blog, with the web middleware. Other locales get a URL prefix (/ru/blog/…), added and
| removed by the CMS kernel (LocalizedUrls), so these routes are defined once.
*/

Route::controller(BlogController::class)->prefix('blog')->name('blog.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/category/{slug}', 'category')->name('category');
    Route::get('/{slug}', 'show')->name('show');
});
