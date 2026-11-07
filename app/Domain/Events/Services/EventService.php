<?php

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use App\Domain\Events\Models\EventResult;
use App\Domain\Teams\Models\Team;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Support\Slugger;
use Illuminate\Support\Facades\DB;

/**
 * The event lifecycle, categories, invitations and results (LLD 06 §5).
 */
final class EventService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $by = null): Event
    {
        return Event::query()->create(array_merge($this->attributes($data), [
            'slug' => Slugger::unique(Event::class, (string) (filled($data['slug'] ?? null) ? $data['slug'] : $data['title_en'])),
            'status' => EventStatus::Draft,
            'timezone' => $data['timezone'] ?? 'Africa/Tripoli',
            'created_by' => $by?->getKey(),
        ]));
    }

    /**
     * Status changes go through transition(), never through update().
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data, ?User $by = null): Event
    {
        $attributes = $this->attributes($data);

        if (filled($data['slug'] ?? null)) {
            $attributes['slug'] = $data['slug'];
        }

        $event->update($attributes);

        return $event;
    }

    public function transition(Event $event, EventStatus $to, ?User $by = null): Event
    {
        if ($to === EventStatus::Open && ! $event->categories()->exists()) {
            throw DomainRuleViolation::with(['status' => __('events.errors.needs_category')]);
        }

        $event->transitionTo($to);

        if ($to === EventStatus::Open && $event->registration_closes_at === null) {
            $event->registration_closes_at = $event->starts_at;
        }

        $event->save();

        return $event;
    }

    /**
     * Replace the event's categories with the given list, in order. A category
     * that already has entries cannot be removed.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function saveCategories(Event $event, array $items, ?User $by = null): void
    {
        DB::transaction(function () use ($event, $items, $by): void {
            $existing = $event->categories()->get()->keyBy('id');
            $keptIds = collect($items)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
            $removed = $existing->except($keptIds);

            $blocked = $removed->filter(fn (EventCategory $category): bool => $category->registrations()->exists());

            if ($blocked->isNotEmpty()) {
                throw DomainRuleViolation::with(['categories' => __('events.errors.category_has_entries', [
                    'names' => $blocked->map(fn (EventCategory $category): string => $category->name_en)->implode(', '),
                ])]);
            }

            EventCategory::query()->whereKey($removed->keys())->delete();

            foreach (array_values($items) as $position => $item) {
                $attributes = [
                    'name_en' => $item['name_en'],
                    'name_ar' => $item['name_ar'] ?? null,
                    'age_min' => $item['age_min'] ?? null,
                    'age_max' => $item['age_max'] ?? null,
                    'members_min' => (int) ($item['members_min'] ?? 1),
                    'members_max' => (int) ($item['members_max'] ?? 10),
                    'robots_per_team' => (int) ($item['robots_per_team'] ?? 1),
                    'capacity' => $item['capacity'] ?? null,
                    'max_weight_g' => $item['max_weight_g'] ?? null,
                    'max_length_mm' => $item['max_length_mm'] ?? null,
                    'max_width_mm' => $item['max_width_mm'] ?? null,
                    'max_height_mm' => $item['max_height_mm'] ?? null,
                    'position' => $position,
                ];

                $category = isset($item['id']) ? $existing->get((int) $item['id']) : null;

                $category !== null
                    ? $category->update($attributes)
                    : $event->categories()->create($attributes);
            }

            activity('events')->performedOn($event)->causedBy($by)
                ->withProperties(['categories' => collect($items)->pluck('name_en')->all()])
                ->event('event.categories')->log('Event categories saved');
        });
    }

    /**
     * @param  list<int>  $teamIds
     */
    public function inviteTeams(Event $event, array $teamIds, ?User $by = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $teamIds)));

        $event->invitedTeams()->syncWithoutDetaching(
            collect($ids)->mapWithKeys(fn (int $id): array => [$id => ['invited_by' => $by?->getKey()]])->all(),
        );

        activity('events')->performedOn($event)->causedBy($by)
            ->withProperties(['team_ids' => $ids])
            ->event('event.invited')->log('Teams invited');
    }

    public function uninviteTeam(Event $event, Team $team, ?User $by = null): void
    {
        $event->invitedTeams()->detach($team->getKey());

        activity('events')->performedOn($event)->causedBy($by)
            ->withProperties(['team_id' => $team->getKey()])
            ->event('event.uninvited')->log('Team invitation removed');
    }

    /**
     * Save results for approved entries. Each row replaces that entry's result.
     *
     * @param  list<array{registration_id: int, placement?: int|null, score?: float|string|null, award_en?: string|null, award_ar?: string|null, notes?: string|null}>  $rows
     */
    public function recordResults(Event $event, array $rows, ?User $by = null): void
    {
        if ($event->status !== EventStatus::Finished) {
            throw DomainRuleViolation::with(['results' => __('events.errors.results_not_finished')]);
        }

        DB::transaction(function () use ($event, $rows, $by): void {
            $approvedIds = $event->registrations()->where('status', RegistrationStatus::Approved)->pluck('id')->all();

            foreach ($rows as $row) {
                if (! in_array((int) $row['registration_id'], $approvedIds, true)) {
                    throw DomainRuleViolation::with(['results' => __('events.errors.result_not_approved')]);
                }

                EventResult::query()->updateOrCreate(
                    ['registration_id' => (int) $row['registration_id']],
                    [
                        'placement' => $row['placement'] ?? null,
                        'score' => $row['score'] ?? null,
                        'award_en' => $row['award_en'] ?? null,
                        'award_ar' => $row['award_ar'] ?? null,
                        'notes' => $row['notes'] ?? null,
                    ],
                );
            }

            activity('events')->performedOn($event)->causedBy($by)
                ->withProperties(['count' => count($rows)])
                ->event('event.results')->log('Event results recorded');
        });
    }

    public function publishResults(Event $event, ?User $by = null): void
    {
        $event->forceFill(['results_published_at' => now()])->save();
    }

    public function delete(Event $event, ?User $by = null): void
    {
        if ($event->status !== EventStatus::Draft) {
            throw DomainRuleViolation::with(['event' => __('events.errors.delete_not_draft')]);
        }

        $event->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->only([
            'department_id', 'title_en', 'title_ar', 'description_en', 'description_ar', 'rules_en', 'rules_ar',
            'terms_en', 'terms_ar', 'venue', 'city', 'timezone', 'starts_at', 'ends_at', 'registration_opens_at',
            'registration_closes_at', 'visibility', 'capacity',
        ])->all();
    }
}
