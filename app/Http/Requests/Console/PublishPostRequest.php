<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Publish now, or at a future time (Scheduled). Times are wall-clock in
 * Libya and stored in UTC.
 */
class PublishPostRequest extends FormRequest
{
    public const TIMEZONE = 'Africa/Tripoli';

    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('post'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['published_at' => ['nullable', 'date']];
    }

    public function publishAt(): ?Carbon
    {
        $value = $this->validated('published_at');

        return filled($value) ? Carbon::parse((string) $value, self::TIMEZONE)->utc() : null;
    }
}
