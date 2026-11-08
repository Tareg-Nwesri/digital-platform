<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Platform settings the console edits today (LLD 08 §1). Field names use
 * underscores; SettingsController maps them to the dotted setting keys.
 * Finance and public-site keys join with their modules.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Form field => setting key.
     *
     * @var array<string, string>
     */
    public const FIELDS = [
        'inbox_department_id' => 'inbox.department_id',
        'events_auto_promote_waitlist' => 'events.auto_promote_waitlist',
        'public_contact_email_display' => 'public.contact_email_display',
        'retention_months' => 'retention.months',
    ];

    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inbox_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'events_auto_promote_waitlist' => ['required', 'boolean'],
            'public_contact_email_display' => ['nullable', 'email:rfc', 'max:190'],
            'retention_months' => ['required', 'integer', 'between:6,120'],
        ];
    }

    /**
     * @return array<string, mixed> setting key => typed value
     */
    public function settings(): array
    {
        $validated = $this->validated();

        return [
            'inbox.department_id' => isset($validated['inbox_department_id']) ? (int) $validated['inbox_department_id'] : null,
            'events.auto_promote_waitlist' => (bool) $validated['events_auto_promote_waitlist'],
            'public.contact_email_display' => $validated['public_contact_email_display'] ?? null,
            'retention.months' => (int) $validated['retention_months'],
        ];
    }
}
