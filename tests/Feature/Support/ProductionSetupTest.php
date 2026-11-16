<?php

use Illuminate\Support\Facades\Route;

/*
 * Go-live settings: behind Hostinger's or Cloudflare's proxy the site sees
 * https, but a client cannot fake its IP address (throttles stay honest).
 */

it('builds https links when the proxy says the request was https', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/en/about')
        ->assertOk()
        ->assertSee('rel="canonical" href="https://', false);
});

it('ignores a forwarded client address', function () {
    Route::get('/_ip', fn () => request()->ip());

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('/_ip')
        ->assertSee('127.0.0.1');
});

it('schedules backups, the queue and the finance checks', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('backup:run')
        ->expectsOutputToContain('backup:clean')
        ->expectsOutputToContain('queue:work')
        ->expectsOutputToContain('finance:verify')
        ->assertSuccessful();
});
