<?php

namespace App\Http\Controllers\Console;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventService;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ChangeEventStatusRequest;
use App\Http\Requests\Console\EventResultsRequest;
use App\Http\Requests\Console\InviteEventTeamsRequest;
use App\Http\Requests\Console\SaveEventCategoriesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Status, categories, invitations and results of an event (LLD 06 §7).
 */
class EventSetupController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function status(ChangeEventStatusRequest $request, Event $event): RedirectResponse
    {
        $status = EventStatus::from($request->validated('status'));
        $this->events->transition($event, $status, $request->user());

        return $this->done('status_changed', ['status' => $status->label()]);
    }

    public function categories(SaveEventCategoriesRequest $request, Event $event): RedirectResponse
    {
        $this->events->saveCategories($event, $request->validated('categories'), $request->user());

        return $this->done('categories_saved');
    }

    public function invite(InviteEventTeamsRequest $request, Event $event): RedirectResponse
    {
        $this->events->inviteTeams($event, $request->validated('team_ids'), $request->user());

        return $this->done('invited');
    }

    public function uninvite(Request $request, Event $event, Team $team): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->events->uninviteTeam($event, $team, $request->user());

        return $this->done('uninvited');
    }

    public function results(EventResultsRequest $request, Event $event): RedirectResponse
    {
        $this->events->recordResults($event, $request->validated('results'), $request->user());

        return $this->done('results_saved');
    }

    public function publishResults(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('review', $event);

        $this->events->publishResults($event, $request->user());

        return $this->done('results_published');
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function done(string $key, array $replace = []): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.'.$key, $replace)]);

        return back();
    }
}
