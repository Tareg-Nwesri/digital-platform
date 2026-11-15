<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A team applying to an event, or accepting an invitation: the members and
 * robots it brings. Ownership, counts and limits are checked by EntryRules.
 */
class EventEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team $team */
        $team = $this->route('team');

        app(TeamFeatures::class)->assertActive($team, TeamFeature::EventsRegister);

        return $this->user()->can('events.apply');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $applying = $event instanceof Event && ! $this->route('registration') instanceof EventRegistration;

        return [
            'category_id' => $applying
                ? ['required', 'integer', Rule::exists('event_categories', 'id')->where('event_id', $event->getKey())]
                : ['prohibited'],
            'member_ids' => ['required', 'array', 'min:1', 'max:50'],
            'member_ids.*' => ['integer', 'distinct'],
            'robot_ids' => ['present', 'array', 'max:10'],
            'robot_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => __('public.register.category'),
            'member_ids' => __('public.register.members'),
            'robot_ids' => __('Robots'),
        ];
    }

    /**
     * @return list<int>
     */
    public function memberIds(): array
    {
        return array_map('intval', $this->input('member_ids', []));
    }

    /**
     * @return list<int>
     */
    public function robotIds(): array
    {
        return array_map('intval', $this->input('robot_ids', []));
    }
}
