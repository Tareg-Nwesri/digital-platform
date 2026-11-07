<?php

namespace App\Http\Requests\Console;

use App\Domain\Events\Enums\EventVisibility;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Create or update an event (LLD 06 §8). The department must be one where
 * the user holds events.edit.
 */
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            ? $this->user()->can('update', $event)
            : $this->user()->can('create', Event::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:140', Rule::unique('events', 'slug')->ignore($event instanceof Event ? $event->getKey() : null)],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'rules_en' => ['nullable', 'string', 'max:20000'],
            'rules_ar' => ['nullable', 'string', 'max:20000'],
            'venue' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'registration_opens_at' => ['nullable', 'date', 'before:registration_closes_at'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'department_id' => ['required', 'integer', Rule::in($this->allowedDepartmentIds())],
        ];
    }

    /**
     * Date-times arrive as wall-clock times in the event's timezone and are
     * stored in UTC.
     */
    protected function prepareForValidation(): void
    {
        $event = $this->route('event');
        $timezone = $event instanceof Event ? $event->timezone : 'Africa/Tripoli';
        $converted = [];

        foreach (['starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '' && strtotime($value) !== false) {
                $converted[$field] = Carbon::parse($value, $timezone)->utc()->format('Y-m-d H:i:s');
            }
        }

        $this->merge($converted);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.in' => __('events.errors.department_not_allowed'),
        ];
    }

    /**
     * @return list<int>
     */
    private function allowedDepartmentIds(): array
    {
        $user = $this->user();

        if ($user->isSuperAdmin()) {
            return Department::query()->pluck('id')->all();
        }

        return app(DepartmentAccess::class)->departmentIdsWith($user, 'events.edit');
    }
}
