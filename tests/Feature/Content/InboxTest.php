<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Notifications\NewContactMessageNotification;
use App\Domain\Content\Services\ContactMessageService;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 08 §1–2, §4–8: contact messages land in the inbox of the department
 * named by the inbox.department_id setting (else the super admin), notify
 * that department's admins, and are worked by inbox staff.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Notification::fake();

    $this->superAdmin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->media = $departments->create(['name_en' => 'Media']);
    $permissions->setCeiling($this->media, ['inbox.view', 'inbox.edit'], $this->superAdmin);
    $this->events = $departments->create(['name_en' => 'Events']);
    $permissions->setCeiling($this->events, ['inbox.view', 'inbox.edit'], $this->superAdmin);

    $this->admin = User::factory()->create(['name_en' => 'Media Lead']);
    $this->agent = User::factory()->create(['name_en' => 'Inbox Agent']);
    $this->reader = User::factory()->create();
    $this->outsider = User::factory()->create();
    $departments->addMember($this->media, $this->admin, isAdmin: true);
    $departments->addMember($this->media, $this->agent);
    $departments->addMember($this->media, $this->reader);
    $departments->addMember($this->events, $this->outsider);
    $permissions->syncMember($this->media, $this->agent, ['inbox.view', 'inbox.edit'], $this->admin);
    $permissions->syncMember($this->media, $this->reader, ['inbox.view'], $this->admin);
    $permissions->syncMember($this->events, $this->outsider, ['inbox.view', 'inbox.edit'], $this->superAdmin);

    $this->service = app(ContactMessageService::class);
});

function contactData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Huda Salem',
        'email' => 'huda@example.test',
        'subject' => 'Joining a team',
        'message' => 'How can my school join?',
        'locale' => 'ar',
        'ip' => '203.0.113.7',
    ], $overrides);
}

it('routes messages to the configured department and notifies its admins', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id, $this->superAdmin);

    $message = $this->service->receive(contactData());

    expect($message->department_id)->toBe($this->media->id)
        ->and($message->status)->toBe(MessageStatus::New)
        ->and($message->ip_hash)->toHaveLength(64)
        ->and($message->ip_hash)->not->toContain('203.0.113.7');

    Notification::assertSentTo($this->admin, NewContactMessageNotification::class);
    Notification::assertNotSentTo($this->agent, NewContactMessageNotification::class);
});

it('sends messages to the super admin when no department is configured', function () {
    $message = $this->service->receive(contactData());

    expect($message->department_id)->toBeNull();
    Notification::assertSentTo($this->superAdmin, NewContactMessageNotification::class);
});

it('shows each department only its own messages', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id, $this->superAdmin);
    $ours = $this->service->receive(contactData());
    app(SettingsRepository::class)->set('inbox.department_id', $this->events->id, $this->superAdmin);
    $theirs = $this->service->receive(contactData(['subject' => 'Sponsorship']));

    $this->actingAs($this->reader)
        ->get(route('console.inbox.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/inbox/Index')
            ->has('messages.data', 1)
            ->where('messages.data.0.subject', 'Joining a team'));

    $this->actingAs($this->reader)->get(route('console.inbox.show', $ours))->assertOk();
    $this->actingAs($this->reader)->get(route('console.inbox.show', $theirs))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('console.inbox.index'))->assertForbidden();
});

it('lets inbox editors assign, change status and add notes', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id, $this->superAdmin);
    $message = $this->service->receive(contactData());

    $this->actingAs($this->reader)->patch(route('console.inbox.update', $message), ['status' => 'closed'])->assertForbidden();

    $this->actingAs($this->agent)
        ->patch(route('console.inbox.update', $message), ['status' => 'in_progress', 'assigned_to' => $this->agent->id])
        ->assertRedirect();
    $this->actingAs($this->agent)
        ->post(route('console.inbox.notes', $message), ['body' => 'Called the school, sending forms.'])
        ->assertRedirect();

    expect($message->fresh())
        ->status->toBe(MessageStatus::InProgress)
        ->assigned_to->toBe($this->agent->id)
        ->and($message->notes()->sole()->body)->toBe('Called the school, sending forms.');

    $this->actingAs($this->agent)
        ->get(route('console.inbox.show', $message))
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/inbox/Show')
            ->where('message.email', 'huda@example.test')
            ->where('message.assignee', 'Inbox Agent')
            ->has('notes', 1)
            ->where('can.edit', true));
});

it('only assigns staff who can see the message', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id, $this->superAdmin);
    $message = $this->service->receive(contactData());

    $this->actingAs($this->agent)
        ->patch(route('console.inbox.update', $message), ['assigned_to' => $this->outsider->id])
        ->assertSessionHasErrors('assigned_to');
});

it('filters the inbox by status', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id, $this->superAdmin);
    $this->service->receive(contactData());
    $closed = $this->service->receive(contactData(['subject' => 'Old question']));
    $this->service->setStatus($closed, MessageStatus::Closed, $this->agent);

    $this->actingAs($this->agent)
        ->get(route('console.inbox.index', ['filter' => ['status' => 'closed']]))
        ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.subject', 'Old question'));
});
