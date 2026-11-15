<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Finance decisions on team funding requests and expenses.
 */
class ReviewBudgetDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approving = $this->routeIs('console.finance.requests.approve', 'console.finance.expenses.approve');

        return Gate::allows($approving ? 'approve-finance' : 'edit-finance');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match (true) {
            $this->routeIs('console.finance.requests.approve') => [
                'amount' => ['nullable', 'decimal:0,3', 'gt:0'],
                'note' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('console.finance.requests.changes') => ['note' => ['required', 'string', 'max:1000']],
            $this->routeIs('console.finance.expenses.approve') => [],
            default => ['reason' => ['required', 'string', 'max:500']],
        };
    }
}
