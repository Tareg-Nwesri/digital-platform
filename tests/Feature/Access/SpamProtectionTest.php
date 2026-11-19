<?php

use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Access\Services\InvitationService;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Honeypot\Honeypot;

/*
 * SRS NFR-SEC-04: login, password reset, invitation acceptance and the public
 * forms are protected by a honeypot (hidden field + minimum fill time). Only the
 * public forms (contact, event registration) also ask Cloudflare Turnstile.
 */

beforeEach(function () {
    config(['honeypot.enabled' => true, 'turnstile.enabled' => true]);
    $GLOBALS['turnstilePasses'] = true;
    $GLOBALS['turnstileReachable'] = true;
    Http::fake(['challenges.cloudflare.com/*' => fn ($request) => $GLOBALS['turnstileReachable']
        ? Http::response($GLOBALS['turnstilePasses'] ? ['success' => true] : ['success' => false, 'error-codes' => ['invalid-input-response']])
        : Http::failedConnection()($request),
    ]);
    $this->user = User::factory()->create(['email' => 'ada@example.com']);
});

/**
 * Valid honeypot fields plus a Turnstile token, as the browser form would send them.
 *
 * @return array<string, string>
 */
function humanFields(): array
{
    $honeypot = new Honeypot(config('honeypot'));

    return [
        $honeypot->nameFieldName() => '',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
        'cf-turnstile-response' => 'token-from-widget',
    ];
}

/**
 * A complete contact form submission.
 *
 * @return array<string, string>
 */
function contactMessage(): array
{
    return ['name' => 'Salma', 'email' => 'salma@example.com', 'subject' => 'Joining', 'message' => 'How can our school join?', 'privacy' => '1'];
}

it('signs in with the honeypot alone, without asking cloudflare', function () {
    $fields = humanFields();
    unset($fields['cf-turnstile-response']);
    $this->travel(5)->seconds();

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...$fields])
        ->assertRedirect();

    $this->assertAuthenticatedAs($this->user);
    Http::assertNothingSent();
});

it('accepts a contact message whose turnstile token cloudflare confirms', function () {
    $this->seed(PermissionCatalogueSeeder::class);
    $fields = humanFields();
    $this->travel(5)->seconds();

    $this->post('/en/contact', [...contactMessage(), ...$fields])->assertRedirect();

    expect(ContactMessage::query()->sole()->email)->toBe('salma@example.com');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'siteverify') && $request['response'] === 'token-from-widget');
});

it('rejects a login whose hidden honeypot field was filled', function () {
    $fields = humanFields();
    $nameField = array_key_first($fields);
    $this->travel(5)->seconds();

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...$fields, $nameField => 'bot'])
        ->assertSessionHasErrors('form');

    $this->assertGuest();
});

it('rejects a login that skips the honeypot fields entirely', function () {
    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', 'cf-turnstile-response' => 'x'])
        ->assertSessionHasErrors('form');

    $this->assertGuest();
});

it('rejects a login submitted faster than a person could type', function () {
    config(['honeypot.amount_of_seconds' => 3]);

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...humanFields()])
        ->assertSessionHasErrors('form');

    $this->assertGuest();
});

it('requires a turnstile token on a public form and rejects one cloudflare refuses', function () {
    $fields = humanFields();
    $this->travel(5)->seconds();
    unset($fields['cf-turnstile-response']);

    $this->post('/en/contact', [...contactMessage(), ...$fields])
        ->assertSessionHasErrors('cf-turnstile-response');

    $GLOBALS['turnstilePasses'] = false;

    $this->post('/en/contact', [...contactMessage(), ...$fields, 'cf-turnstile-response' => 'bad'])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(ContactMessage::query()->exists())->toBeFalse();
});

it('rejects a public form after two quick attempts when cloudflare cannot be reached', function () {
    Sleep::fake();
    $GLOBALS['turnstileReachable'] = false;
    $fields = humanFields();
    $this->travel(5)->seconds();

    $this->post('/en/contact', [...contactMessage(), ...$fields])
        ->assertSessionHasErrors('cf-turnstile-response');

    expect(ContactMessage::query()->exists())->toBeFalse();
    Sleep::assertSleptTimes(1);
});

it('starts loading the turnstile script with the page on a public form', function () {
    $this->get('/en/contact')
        ->assertOk()
        ->assertSee('<link rel="preconnect" href="https://challenges.cloudflare.com">', false)
        ->assertSee('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>', false);
});

it('leaves the turnstile script off sign-in and pages without a public form', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertDontSee('challenges.cloudflare.com', false);
})->with(['sign-in' => '/login', 'about' => '/en/about']);

it('leaves the turnstile script off while turnstile is switched off', function () {
    config(['turnstile.enabled' => false]);

    $this->get('/en/contact')
        ->assertOk()
        ->assertDontSee('challenges.cloudflare.com', false);
});

it('protects the password reset request and the invitation acceptance too', function () {
    Notification::fake();
    $team = Team::factory()->create();
    $token = null;
    app(InvitationService::class)->invite($team->workspace, 'new@example.com', 'member');
    Notification::assertSentTo(new AnonymousNotifiable, InvitationNotification::class, function ($n) use (&$token) {
        $token = $n->plainToken;

        return true;
    });

    $this->post(route('password.email'), ['email' => 'ada@example.com'])->assertSessionHasErrors('form');
    $this->post(route('invitations.accept', $token), [
        'name_en' => 'Bot', 'password' => 'robotics2026', 'password_confirmation' => 'robotics2026',
    ])->assertSessionHasErrors('form');

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('shares the honeypot fields and the turnstile site key with the login page', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('honeypot.enabled', true)
        ->has('honeypot.nameFieldName')
        ->has('honeypot.encryptedValidFrom')
        ->where('turnstile.siteKey', config('turnstile.sitekey')));
});
