<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * SRS FR-ACC-10, NFR-L10N-01, UI-01/08: the platform is Arabic and
 * right-to-left first. The interface follows the user's language, else the
 * locale cookie, else the configured default (Arabic). The choice is kept
 * on the account and in a cookie, and switching reloads the whole page so
 * <html lang dir> always matches.
 */

it('ships Arabic as the default language', function () {
    expect(file_get_contents(config_path('app.php')))->toContain("'locale' => env('APP_LOCALE', 'ar')")
        ->and(file_get_contents(base_path('.env.example')))->toContain('APP_LOCALE=ar');
});

it('renders guests in arabic right-to-left by default', function () {
    config(['app.locale' => 'ar']);

    $this->get(route('login'))
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('dir', 'rtl'));
});

it('follows the configured default when it is english', function () {
    config(['app.locale' => 'en']);

    $this->get(route('login'))
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('dir', 'ltr'));
});

it('renders the app in the signed-in user\'s language and direction', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->get(route('hub'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('dir', 'rtl'));
});

it('uses the cookie and then the default for users who never chose a language', function () {
    config(['app.locale' => 'ar']);
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)->get(route('hub'))->assertSee('<html lang="ar" dir="rtl"', false);

    $this->actingAs($user)->withUnencryptedCookie('locale', 'en')
        ->get(route('hub'))
        ->assertSee('<html lang="en" dir="ltr"', false);
});

it('creates users without a fixed language, so they follow the default', function () {
    $user = User::query()->create(['name_en' => 'New Member', 'email' => 'new@example.test', 'password' => 'secret-pass-1']);

    expect($user->fresh()->locale)->toBeNull()
        ->and($user->fresh()->preferredLocale())->toBe(config('app.locale'));
});

it('uses the locale cookie for guests', function () {
    config(['app.locale' => 'en']);

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

it('reloads the whole page when the switch comes from the app, so the direction changes', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->from(route('hub'))
        ->post(route('locale.update', 'ar'), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('hub'));
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
