<?php

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Event cover photo and downloadable documents (requirements, rules,
 * forms): organisers upload them in the console; visitors see the cover
 * and download the documents from the public event page.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->seed([PermissionCatalogueSeeder::class, TierSeeder::class]);

    $this->admin = User::factory()->create();
    app(WorkspaceContext::class)->within(Workspace::platform(), fn () => $this->admin->assignRole(User::SUPER_ADMIN));

    $this->department = app(DepartmentService::class)->create(['name_en' => 'Events']);
    $this->event = Event::factory()->for($this->department)->open()->create(['slug' => 'robot-cup']);
});

it('uploads and replaces the cover photo', function () {
    $this->actingAs($this->admin)
        ->post(route('console.events.cover', 'robot-cup'), ['cover' => UploadedFile::fake()->image('first.jpg', 1600, 900)])
        ->assertRedirect();
    $this->actingAs($this->admin)
        ->post(route('console.events.cover', 'robot-cup'), ['cover' => UploadedFile::fake()->image('second.png', 1200, 600)])
        ->assertRedirect();

    $media = $this->event->fresh()->getMedia('cover');
    expect($media)->toHaveCount(1)
        ->and($media->first()->file_name)->toBe('second.png')
        ->and($media->first()->hasGeneratedConversion('wide'))->toBeTrue();
});

it('removes the cover photo', function () {
    $this->event->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');

    $this->actingAs($this->admin)->delete(route('console.events.cover.destroy', 'robot-cup'))->assertRedirect();

    expect($this->event->fresh()->getMedia('cover'))->toBeEmpty();
});

it('refuses a cover that is not an image', function () {
    $this->actingAs($this->admin)
        ->post(route('console.events.cover', 'robot-cup'), ['cover' => UploadedFile::fake()->create('rules.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('cover');
});

it('uploads documents with a title and deletes them', function () {
    $this->actingAs($this->admin)
        ->post(route('console.events.documents.store', 'robot-cup'), [
            'document' => UploadedFile::fake()->create('requirements.pdf', 300, 'application/pdf'),
            'title' => 'Competition requirements',
        ])
        ->assertRedirect();

    $document = $this->event->fresh()->getFirstMedia('documents');
    expect($document->name)->toBe('Competition requirements');

    $this->actingAs($this->admin)->delete(route('console.events.documents.destroy', ['robot-cup', $document->getKey()]))->assertRedirect();

    expect($this->event->fresh()->getMedia('documents'))->toBeEmpty();
});

it('refuses unsafe or oversized documents', function (UploadedFile $file) {
    $this->actingAs($this->admin)
        ->post(route('console.events.documents.store', 'robot-cup'), ['document' => $file])
        ->assertSessionHasErrors('document');
})->with([
    'script' => fn () => UploadedFile::fake()->create('evil.php', 1, 'application/x-php'),
    'too big' => fn () => UploadedFile::fake()->create('huge.pdf', 21 * 1024, 'application/pdf'),
]);

it('lets only event editors manage media', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('console.events.documents.store', 'robot-cup'), ['document' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
        ->assertForbidden();
});

it('refuses deleting a document of another event', function () {
    $other = Event::factory()->for($this->department)->create(['slug' => 'other-cup']);
    $document = $other->addMedia(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    $this->actingAs($this->admin)->delete(route('console.events.documents.destroy', ['robot-cup', $document->getKey()]))->assertNotFound();
});

it('shows the cover and the documents in the console and on the public pages', function () {
    $this->event->addMedia(UploadedFile::fake()->image('cover.jpg', 1600, 900))->toMediaCollection('cover');
    $this->event->addMedia(UploadedFile::fake()->create('requirements.pdf', 300, 'application/pdf'))
        ->usingName('Requirements')->toMediaCollection('documents');

    $this->actingAs($this->admin)->get(route('console.events.show', 'robot-cup'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereType('event.cover', 'string')
            ->where('event.documents.0.name', 'Requirements'));

    $this->get('/en/events/robot-cup')
        ->assertInertia(fn (Assert $page) => $page
            ->whereType('event.cover', 'string')
            ->where('event.documents.0.name', 'Requirements')
            ->where('event.documents.0.extension', 'pdf'));

    $this->get('/ar/events')
        ->assertInertia(fn (Assert $page) => $page->whereType('events.0.cover', 'string'));

    $this->get('/en/events/robot-cup/register')
        ->assertInertia(fn (Assert $page) => $page->has('event.documents', 1));
});

it('lets visitors download documents of visible events only', function () {
    $document = $this->event->addMedia(UploadedFile::fake()->create('requirements.pdf', 30, 'application/pdf'))
        ->usingName('Requirements')->toMediaCollection('documents');

    $this->get("/en/events/robot-cup/documents/{$document->getKey()}")
        ->assertOk()
        ->assertDownload('requirements.pdf');

    $draft = Event::factory()->for($this->department)->create(['slug' => 'draft-cup']);
    $hidden = $draft->addMedia(UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    $this->get("/en/events/draft-cup/documents/{$hidden->getKey()}")->assertNotFound();
    $this->get("/en/events/robot-cup/documents/{$hidden->getKey()}")->assertNotFound();
});
