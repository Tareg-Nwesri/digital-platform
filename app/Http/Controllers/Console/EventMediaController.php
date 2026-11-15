<?php

namespace App\Http\Controllers\Console;

use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\StoreEventCoverRequest;
use App\Http\Requests\Console\StoreEventDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * The event's cover photo and its downloadable documents.
 */
class EventMediaController extends Controller
{
    public function cover(StoreEventCoverRequest $request, Event $event): RedirectResponse
    {
        $event->addMediaFromRequest('cover')->toMediaCollection('cover');
        $this->log($event, $request, 'event.cover_changed');

        return $this->done('cover_saved');
    }

    public function destroyCover(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        $event->clearMediaCollection('cover');
        $this->log($event, $request, 'event.cover_removed');

        return $this->done('cover_removed');
    }

    public function storeDocument(StoreEventDocumentRequest $request, Event $event): RedirectResponse
    {
        $file = $request->file('document');
        $title = filled($request->input('title'))
            ? Str::squish((string) $request->input('title'))
            : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $event->addMediaFromRequest('document')
            ->usingName($title)
            ->sanitizingFileName(fn (string $name): string => Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.Str::lower($file->getClientOriginalExtension()))
            ->toMediaCollection('documents');
        $this->log($event, $request, 'event.document_added', ['name' => $title]);

        return $this->done('document_saved');
    }

    public function destroyDocument(Request $request, Event $event, int $media): RedirectResponse
    {
        $this->authorize('update', $event);
        $document = $event->media()->where('collection_name', 'documents')->findOrFail($media);
        $document->delete();
        $this->log($event, $request, 'event.document_removed', ['name' => $document->name]);

        return $this->done('document_removed');
    }

    /**
     * @param  array<string, string>  $properties
     */
    private function log(Event $event, Request $request, string $name, array $properties = []): void
    {
        activity('events')->performedOn($event)->causedBy($request->user())
            ->withProperties($properties)->event($name)->log(Str::headline(Str::after($name, 'event.')));
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.'.$key)]);

        return back();
    }
}
