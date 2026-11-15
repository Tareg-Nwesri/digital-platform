<?php

namespace App\Http\Requests\Public;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A visitor's team registration for a public event. Member counts come from
 * the chosen category; ages are checked by the entry rules.
 */
class RegisterTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        abort_unless($event instanceof Event && $event->isVisibleToVisitors(), 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');
        $category = $this->category();

        return [
            'category_id' => ['required', 'integer', Rule::exists('event_categories', 'id')->where('event_id', $event->getKey())],
            'team_name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'string', 'email', 'max:190'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'robot_name' => ['nullable', 'string', 'max:120'],
            'members' => ['required', 'array', 'min:'.($category->members_min ?? 1), 'max:'.($category->members_max ?? 10)],
            'members.*.name' => ['required', 'string', 'max:120'],
            'members.*.birthdate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => __('public.register.category'),
            'team_name' => __('public.register.team_name'),
            'city' => __('public.register.city'),
            'contact_name' => __('public.register.contact_name'),
            'contact_email' => __('public.register.contact_email'),
            'contact_phone' => __('public.register.contact_phone'),
            'robot_name' => __('public.register.robot_name'),
            'members' => __('public.register.members'),
            'members.*.name' => __('public.register.member_name'),
            'members.*.birthdate' => __('public.register.member_birthdate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $count = __('public.register.members_count', [
            'min' => $this->category()->members_min ?? 1,
            'max' => $this->category()->members_max ?? 10,
        ]);

        return [
            'members.min' => $count,
            'members.max' => $count,
            'contact_phone.regex' => __('public.register.phone_format'),
        ];
    }

    private function category(): ?EventCategory
    {
        /** @var Event $event */
        $event = $this->route('event');

        return once(fn (): ?EventCategory => EventCategory::query()
            ->whereKey($this->integer('category_id'))
            ->where('event_id', $event->getKey())
            ->first());
    }
}
