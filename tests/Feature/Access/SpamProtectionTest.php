<?php

use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Access\Services\InvitationService;
use App\Domain\Teams\Models\Team;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Honeypot\Honeypot;

/*
 * SRS NFR-SEC-04: login, password reset and invitation acceptance are protected by
 * a honeypot (hidden field + minimum fill time) and Cloudflare Turnstile.
 */

beforeEach(function () {
    config(['honeypot.enabled' => true, 'turnstile.enabled' => true]);
    $GLOBALS['turnstilePasses'] = true;
    Http::fake(['challenges.cloudflare.com/*' => fn () => Http::response(
        $GLOBALS['turnstilePasses'] ? ['success' => true] : ['success' => false, 'error-codes' => ['invalid-input-response']],
    )]);
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

it('signs in a person who passes the honeypot and turnstile', function () {
    $fields = humanFields();
    $this->travel(5)->seconds();

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...$fields])
        ->assertRedirect();

    $this->assertAuthenticatedAs($this->user);
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

it('requires a turnstile token and rejects one cloudflare refuses', function () {
    $fields = humanFields();
    $this->travel(5)->seconds();
    unset($fields['cf-turnstile-response']);

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...$fields])
        ->assertSessionHasErrors('cf-turnstile-response');

    $GLOBALS['turnstilePasses'] = false;

    $this->post(route('login.store'), ['email' => 'ada@example.com', 'password' => 'password', ...$fields, 'cf-turnstile-response' => 'bad'])
        ->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
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
