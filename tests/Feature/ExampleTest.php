<?php

use Illuminate\Support\Facades\DB;

it('runs against the odincms_test database', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('odincms_test');
});

it('returns a successful response for the home page', function () {
    $this->get('/')->assertOk();
});
