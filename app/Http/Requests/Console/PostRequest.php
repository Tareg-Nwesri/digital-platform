<?php

namespace App\Http\Requests\Console;

use App\Domain\Content\Models\Post;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post
            ? $this->user()->can('update', $post)
            : $this->user()->can('create', Post::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::in($this->allowedDepartmentIds())],
            'title_en' => ['required', 'string', 'max:160'],
            'title_ar' => ['nullable', 'string', 'max:160'],
            'excerpt_en' => ['nullable', 'string', 'max:400'],
            'excerpt_ar' => ['nullable', 'string', 'max:400'],
            'body_en' => ['nullable', 'string', 'max:100000'],
            'body_ar' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['department_id.in' => __('content.errors.department_not_allowed')];
    }

    /**
     * @return list<int>
     */
    private function allowedDepartmentIds(): array
    {
        return $this->user()->isSuperAdmin()
            ? Department::query()->pluck('id')->all()
            : app(DepartmentAccess::class)->departmentIdsWith($this->user(), 'content.edit');
    }
}
