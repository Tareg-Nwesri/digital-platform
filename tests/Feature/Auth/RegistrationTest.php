<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('self-registration is disabled because accounts are created by invitation', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});
