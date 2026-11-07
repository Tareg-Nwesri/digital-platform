<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventResultsRequest extends FormRequest
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
            'results' => ['required', 'array', 'max:500'],
            'results.*.registration_id' => ['required', 'integer', 'distinct', Rule::exists('event_registrations', 'id')->where('event_id', $this->route('event')->getKey())],
            'results.*.placement' => ['nullable', 'integer', 'between:1,500'],
            'results.*.score' => ['nullable', 'numeric', 'between:-99999999,99999999'],
            'results.*.award_en' => ['nullable', 'string', 'max:120'],
            'results.*.award_ar' => ['nullable', 'string', 'max:120'],
            'results.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
