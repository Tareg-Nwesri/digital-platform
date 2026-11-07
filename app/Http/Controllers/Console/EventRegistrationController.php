<?php

namespace App\Http\Controllers\Console;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Services\RegistrationService;
use App\Domain\Teams\Models\Team;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\AddEventTeamRequest;
use App\Http\Requests\Console\ApproveRegistrationRequest;
use App\Http\Requests\Console\RejectRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Organiser decisions on event entries (LLD 06 §7).
 */
class EventRegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function store(AddEventTeamRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();

        $registration = $this->registrations->addByOrganiser(
            $event,
            EventCategory::query()->findOrFail($data['category_id']),
            Team::query()->findOrFail($data['team_id']),
            array_map('intval', $data['member_ids']),
            (bool) ($data['as_invite'] ?? false),
            $data['override_reason'] ?? null,
            $request->user(),
        );

        return $this->done(match ($registration->status) {
            RegistrationStatus::Invited => 'team_invited',
            RegistrationStatus::Waitlisted => 'team_waitlisted',
            default => 'team_added',
        });
    }

    public function approve(ApproveRegistrationRequest $request, EventRegistration $registration): RedirectResponse
    {
        $result = $this->registrations->approve($registration, $request->validated('override_reason'), $request->user());

        return $this->done($result->status === RegistrationStatus::Waitlisted ? 'team_waitlisted' : 'approved');
    }

    public function reject(RejectRegistrationRequest $request, EventRegistration $registration): RedirectResponse
    {
        $this->registrations->reject($registration, $request->validated('reason'), $request->user());

        return $this->done('rejected');
    }

    public function waitlist(Request $request, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('review', $registration->event);

        $this->registrations->waitlist($registration, $request->user());

        return $this->done('team_waitlisted');
    }

    public function withdraw(Request $request, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('update', $registration->event);

        $this->registrations->withdraw($registration, $request->user(), byOrganiser: true);

        return $this->done('withdrawn');
    }

    public function promote(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('review', $event);

        $promoted = $this->registrations->promoteFromWaitlist($event, $request->user());

        if ($promoted === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('events.flash.nothing_to_promote')]);

            return back();
        }

        return $this->done('promoted', ['team' => $promoted->team->name]);
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
