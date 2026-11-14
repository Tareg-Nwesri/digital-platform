<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Work\Enums\RobotStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RobotService checks that the project belongs to the same team.
 */
class RobotRequest extends FormRequest
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
        $size = ['nullable', 'integer', 'min:1', 'max:1000000'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:3000'],
            'description_ar' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::enum(RobotStatus::class)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'weight_g' => $size,
            'length_mm' => $size,
            'width_mm' => $size,
            'height_mm' => $size,
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
