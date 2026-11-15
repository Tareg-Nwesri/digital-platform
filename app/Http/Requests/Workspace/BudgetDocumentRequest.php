<?php

namespace App\Http\Requests\Workspace;

use App\Support\Enums\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A team's funding request or expense (LLD 07 §6). Fund, project and
 * budget ownership are checked by the services.
 */
class BudgetDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('budget.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->routeIs('workspace.budget.expenses.store')) {
            return [
                'budget_account_id' => ['required', 'integer'],
                'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
                'vendor_name' => ['nullable', 'required_without:vendor_id', 'string', 'max:160'],
                'date' => ['required', 'date', 'before_or_equal:today'],
                'amount' => ['required', 'decimal:0,3', 'gt:0', 'max:99999999'],
                'items' => ['required', 'array', 'min:1', 'max:50'],
                'items.*.description' => ['required', 'string', 'max:160'],
                'items.*.amount' => ['required', 'decimal:0,3', 'gt:0'],
                'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ];
        }

        return [
            'project_id' => ['nullable', 'integer'],
            'amount' => ['required', 'decimal:0,3', 'gt:0', 'max:99999999'],
            'currency' => ['required', Rule::in(Currency::values())],
            'purpose' => ['required', 'string', 'max:1000'],
            'breakdown' => ['present', 'array', 'max:30'],
            'breakdown.*.label' => ['required', 'string', 'max:120'],
            'breakdown.*.amount' => ['required', 'decimal:0,3', 'gt:0'],
            'source_account_id' => ['required', 'integer'],
        ];
    }
}
