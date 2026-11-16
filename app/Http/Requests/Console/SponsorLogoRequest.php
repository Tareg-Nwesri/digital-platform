<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A sponsor's logo. Raster images only: SVG can carry scripts that would
 * run on our domain when the file is opened directly.
 */
class SponsorLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('edit-finance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', 'dimensions:min_width=64,min_height=64'],
        ];
    }
}
