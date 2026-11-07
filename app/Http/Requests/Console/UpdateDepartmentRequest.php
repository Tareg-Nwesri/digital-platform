<?php

namespace App\Http\Requests\Console;

class UpdateDepartmentRequest extends StoreDepartmentRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('department'));
    }
}
