<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('robots.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.part_id' => ['required', 'integer', Rule::exists('parts', 'id')],
            'lines.*.vendor_offer_id' => ['nullable', 'integer', Rule::exists('vendor_offers', 'id')],
            'lines.*.quantity' => ['required', 'integer', 'between:1,10000'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
