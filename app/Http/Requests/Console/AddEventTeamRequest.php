<?php

namespace App\Http\Requests\Console;

use App\Domain\Teams\Enums\TeamStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An organiser adds a team to an event (LLD 06 §8). The entry rules run in
 * the service; this only checks the shape of the request.
 */
class AddEventTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where(fn ($query) => $query
                ->where('status', '!=', TeamStatus::Archived->value)
                ->whereNull('deleted_at'))],
            'category_id' => ['required', 'integer', Rule::exists('event_categories', 'id')->where('event_id', $this->route('event')->getKey())],
            'member_ids' => ['required', 'array', 'min:1', 'max:20'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'as_invite' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => __('events.errors.category_mismatch'),
        ];
    }
}
