<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Services\ContactMessageService;
use App\Domain\Content\Services\PostService;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * LLD 08 §3: every activity entry carries its workspace and, while someone
 * is acting as another user, the impersonator. Departments get a report of
 * their areas (reports.view), exportable as CSV (reports.edit); the super
 * admin sees the whole audit log.
 */

beforeEach(function () {
    $this->seed(PermissionCatalogueSeeder::class);
    Notification::fake();
    Carbon::setTestNow('2027-03-15 10:00:00');

    $this->superAdmin = User::factory()->create(['name_en' => 'Platform Admin']);
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->superAdmin->assignRole(User::SUPER_ADMIN));
    $this->superAdmin = $this->superAdmin->fresh();

    $departments = app(DepartmentService::class);
    $permissions = app(DepartmentPermissionService::class);
    $this->media = $departments->create(['name_en' => 'Media']);
    $permissions->setCeiling($this->media, ['content.view', 'content.edit', 'inbox.view', 'inbox.edit', 'events.view', 'events.edit', 'reports.view', 'reports.edit'], $this->superAdmin);

    $this->admin = User::factory()->create(['name_en' => 'Media Lead']);
    $this->analyst = User::factory()->create(['name_en' => 'Analyst']);
    $this->editor = User::factory()->create(['name_en' => 'Editor']);
    $departments->addMember($this->media, $this->admin, isAdmin: true);
    $departments->addMember($this->media, $this->analyst);
    $departments->addMember($this->media, $this->editor);
    $permissions->syncMember($this->media, $this->analyst, ['reports.view'], $this->admin);
    $permissions->syncMember($this->media, $this->editor, ['content.view', 'content.edit'], $this->admin);
});

it('stamps activity with the workspace and the impersonator', function () {
    $target = User::factory()->create();

    $this->actingAs($this->superAdmin)->post(route('impersonate.take', $target));
    $this->from(route('hub'))->post(route('locale.update', 'en'));

    $entry = Activity::query()
        ->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($entry?->impersonator_id)->toBe($this->superAdmin->id)
        ->and($entry?->causer_id)->toBe($target->id);

    $context = app(WorkspaceContext::class);
    $context->set($this->media->workspace, $this->media);
    activity('test')->log('Inside media');
    $context->clear();

    expect(Activity::query()->where('description', 'Inside media')->sole()->workspace_id)->toBe($this->media->workspace_id);
});

it('never writes passwords or two-factor secrets into activity', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-brand-new-secret-9', 'name_en' => 'Renamed']);
    $user->forceFill(['two_factor_secret' => 'TOPSECRET'])->save();

    $dump = Activity::query()->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->get()->toJson();

    expect($dump)->toContain('Renamed')
        ->not->toContain('a-brand-new-secret-9')
        ->not->toContain('TOPSECRET')
        ->not->toContain('password');
});

it('shows the department report with counts for its areas', function () {
    app(SettingsRepository::class)->set('inbox.department_id', $this->media->id);
    $messages = app(ContactMessageService::class);
    $messages->receive(['name' => 'A', 'email' => 'a@example.test', 'subject' => 'One', 'message' => 'Hi']);
    $closed = $messages->receive(['name' => 'B', 'email' => 'b@example.test', 'subject' => 'Two', 'message' => 'Hi']);
    $messages->setStatus($closed, MessageStatus::Closed, $this->admin);

    $posts = app(PostService::class);
    $post = $posts->create(['department_id' => $this->media->id, 'title_en' => 'News'], $this->editor);
    $posts->publish($post, null, $this->admin);

    Event::factory()->for($this->media)->create(['status' => EventStatus::Open]);

    $this->actingAs($this->analyst)
        ->get(route('console.reports.department', [$this->media, 'from' => '2027-03-01', 'to' => '2027-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/reports/Department')
            ->where('department.name', 'Media')
            ->where('report.inbox.new', 1)
            ->where('report.inbox.closed', 1)
            ->where('report.content.published', 1)
            ->where('report.events.byStatus.open', 1)
            ->has('report.activity.data')
            ->where('can.export', false));
});

it('exports the report as CSV for reports.edit holders only', function () {
    $this->actingAs($this->analyst)
        ->get(route('console.reports.department', [$this->media, 'format' => 'csv']))
        ->assertForbidden();

    $response = $this->actingAs($this->admin)
        ->get(route('console.reports.department', [$this->media, 'format' => 'csv', 'from' => '2027-03-01', 'to' => '2027-03-31']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Media')->toContain('2027-03-01');
});

it('keeps reports to the department’s own staff', function () {
    $this->actingAs($this->editor)->get(route('console.reports.department', $this->media))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('console.reports.department', $this->media))->assertForbidden();
});

it('lets the super admin search the whole audit log', function () {
    activity('finance')->causedBy($this->admin)->event('finance.test')->log('Something financial');
    activity('content')->causedBy($this->editor)->event('content.test')->log('Something editorial');

    $this->actingAs($this->superAdmin)
        ->get(route('console.audit.index', ['filter' => ['log' => 'finance']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('console/audit/Index')
            ->where('entries.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['description'] === 'Something financial' && $row['causer'] === 'Media Lead'))
            ->where('entries.data', fn ($rows) => collect($rows)->doesntContain(fn ($row) => $row['description'] === 'Something editorial')));

    $this->actingAs($this->admin)->get(route('console.audit.index'))->assertForbidden();
});
