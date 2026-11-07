<?php

namespace App\Http\Requests\Console;

use App\Domain\Catalogue\Models\PartCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Categories are two levels deep at most (LLD 05 §1).
 */
class PartCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-catalogue');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', Rule::exists('part_categories', 'id')],
            'position' => ['nullable', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $parentId = $this->integer('parent_id') ?: null;
                $current = $this->route('category');

                if ($parentId === null) {
                    return;
                }

                $parent = PartCategory::query()->find($parentId);
                $isOwnParent = $current instanceof PartCategory && $current->getKey() === $parentId;
                $hasChildren = $current instanceof PartCategory && $current->children()->exists();

                if ($parent === null || $parent->parent_id !== null || $isOwnParent || $hasChildren) {
                    $validator->errors()->add('parent_id', __('catalogue.errors.category_depth'));
                }
            },
        ];
    }
}
