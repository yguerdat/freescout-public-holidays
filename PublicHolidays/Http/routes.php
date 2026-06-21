<?php

// Admin / web routes.
Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\PublicHolidays\Http\Controllers'], function () {

    Route::get('/app/publicholidays/settings', ['uses' => 'PublicHolidaysController@settings', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('publicholidays.settings');
    Route::post('/app/publicholidays/settings', ['uses' => 'PublicHolidaysController@settingsSave', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']]);

    // AJAX (admin only).
    Route::post('/app/publicholidays/generate', ['uses' => 'PublicHolidaysController@generate', 'middleware' => ['auth', 'roles'], 'roles' => ['admin'], 'laroute' => true])->name('publicholidays.generate');
    Route::post('/app/publicholidays/holiday', ['uses' => 'PublicHolidaysController@storeHoliday', 'middleware' => ['auth', 'roles'], 'roles' => ['admin'], 'laroute' => true])->name('publicholidays.store_holiday');
    Route::post('/app/publicholidays/holiday/delete', ['uses' => 'PublicHolidaysController@deleteHoliday', 'middleware' => ['auth', 'roles'], 'roles' => ['admin'], 'laroute' => true])->name('publicholidays.delete_holiday');
});

// Public REST API (authenticated with the FreeScout API key).
Route::group([
    'middleware' => ['bindings', \Modules\PublicHolidays\Http\Middleware\ApiAuth::class],
    'prefix' => \Helper::getSubdirectory(true) . 'api/publicholidays',
    'namespace' => 'Modules\PublicHolidays\Http\Controllers',
], function () {
    Route::options('{all}', 'ApiController@options')->where('all', '.*');

    // Today's (or a given date's) office status.
    Route::get('/status', 'ApiController@status')->name('publicholidays.api.status');
    // List holidays for a year.
    Route::get('/', 'ApiController@index')->name('publicholidays.api.index');
    // Upcoming holidays.
    Route::get('/upcoming', 'ApiController@upcoming')->name('publicholidays.api.upcoming');
});
