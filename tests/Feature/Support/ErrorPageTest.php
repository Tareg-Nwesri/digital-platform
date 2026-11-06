<?php

use App\Domain\Teams\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * SRS UI-07: 403, 404, 419, 429, 500 and 503 are one translated Vue page
 * that never shows exception details and links back to a sensible place.
 */

it('renders a translated 404 page that links signed-in people to their hub', function () {
    $this->actingAs(User::factory()->create())
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')
            ->where('status', 404)
            ->where('title', 'Page not found')
            ->where('homeUrl', route('hub')));
});

it('links guests to the home page', function () {
    $this->get('/nowhere')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->where('homeUrl', route('home')));
});

it('speaks arabic to arabic users', function () {
    $this->actingAs(User::factory()->create(['locale' => 'ar']))
        ->get('/nowhere')
        ->assertInertia(fn (Assert $page) => $page->where('title', 'الصفحة غير موجودة'));
});

it('hides other teams behind the same 404 page', function () {
    $team = Team::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('workspace.dashboard', $team->slug))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});

it('never exposes exception messages', function (int $status) {
    Route::middleware('web')->get('/boom-'.$status, fn () => abort($status, 'secret internal detail'));

    $this->get('/boom-'.$status)
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', $status))
        ->assertDontSee('secret internal detail');
})->with([403, 404, 419, 429, 503]);

it('renders server errors on the same page when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/explode', fn () => throw new RuntimeException('database password is hunter2'));

    $this->get('/explode')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 500))
        ->assertDontSee('hunter2');
});
