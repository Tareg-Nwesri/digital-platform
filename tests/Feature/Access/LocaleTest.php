<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * SRS FR-ACC-10, NFR-L10N-01, UI-01/08: the interface follows the user's language,
 * Arabic renders right-to-left, and the choice is kept on the account and in a cookie.
 */

it('renders the app in the signed-in user\'s language and direction', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $response = $this->actingAs($user)->get(route('hub'));

    $response->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('dir', 'rtl'));
});

it('defaults guests to english left-to-right', function () {
    $this->get(route('login'))
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('dir', 'ltr'));
});

it('uses the locale cookie for guests', function () {
    $this->withUnencryptedCookie('locale', 'ar')
        ->get(route('login'))
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

it('saves a signed-in user\'s language choice on the account and in a cookie', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->from(route('hub'))
        ->post(route('locale.update', 'ar'))
        ->assertRedirect(route('hub'))
        ->assertCookie('locale', 'ar', false);

    expect($user->fresh()->locale)->toBe('ar');
});

it('lets guests switch language with the cookie only', function () {
    $this->from(route('login'))
        ->post(route('locale.update', 'ar'))
        ->assertRedirect(route('login'))
        ->assertCookie('locale', 'ar', false);
});

it('rejects languages the platform does not support', function () {
    $this->post('/locale/fr')->assertNotFound();
});

it('translates server messages into arabic for arabic users', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->get(route('hub'));

    expect(__('access.roles.captain'))->toBe('القائد');
});
