<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.edit');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'excerpt_en' => ['nullable', 'string', 'max:400'],
            'excerpt_ar' => ['nullable', 'string', 'max:400'],
            'body_en' => ['nullable', 'string', 'max:100000'],
            'body_ar' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
