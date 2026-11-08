<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A reason for rejecting or reversing a transaction, or for overriding a
 * negative balance on approval (optional there).
 */
class FinanceReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve-finance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = str($this->route()?->getName() ?? '')->endsWith('.approve') ? 'nullable' : 'required';

        return [
            'reason' => [$required, 'string', 'max:500'],
        ];
    }
}
