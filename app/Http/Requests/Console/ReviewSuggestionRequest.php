<?php

namespace App\Http\Requests\Console;

use App\Domain\Catalogue\Enums\CatalogueStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approve (with optional edits), reject (with a note) or merge a suggestion
 * (LLD 05 §7). The action is the last segment of the route name.
 */
class ReviewSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review-catalogue');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = (string) $this->route('type');

        return match ($this->action()) {
            'reject' => ['note' => ['required', 'string', 'max:500']],
            'merge' => ['into_id' => ['required', 'integer', Rule::exists($type === 'part' ? 'parts' : 'vendors', 'id')
                ->where('status', CatalogueStatus::Approved->value)]],
            default => [
                'edits' => ['nullable', 'array'],
                ...$this->editRules($type),
            ],
        };
    }

    public function action(): string
    {
        return (string) str($this->route()?->getName() ?? '')->afterLast('.');
    }

    /**
     * Edits are optional, so each field is "sometimes" on top of its usual rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function editRules(string $type): array
    {
        $rules = match ($type) {
            'part' => PartRequest::fieldRules('edits.'),
            'vendor' => VendorRequest::fieldRules('edits.'),
            default => array_diff_key(SyncOffersRequest::fieldRules('edits.'), ['edits.vendor_id' => true]),
        };

        return array_map(fn (array $fieldRules): array => ['sometimes', ...$fieldRules], $rules);
    }
}
