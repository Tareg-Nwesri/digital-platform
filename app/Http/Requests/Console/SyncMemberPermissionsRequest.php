<?php

namespace App\Http\Requests\Console;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The ceiling and delegation rules are checked in DepartmentPermissionService.
 */
class SyncMemberPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('grant', [$this->route('department'), $this->route('user')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', 'max:80'],
        ];
    }
}
