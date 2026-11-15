<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor's message from the contact page. Bots are stopped earlier by
 * ProtectAgainstBots (honeypot and Turnstile) and the throttle.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('public_site.name'),
            'email' => __('public_site.email'),
            'subject' => __('public_site.subject'),
            'message' => __('public_site.message'),
            'privacy' => __('public_site.privacy'),
        ];
    }
}
