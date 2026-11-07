<?php

namespace App\Http\Requests\Console;

use App\Domain\Teams\Enums\TeamStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTeamStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('team'));
    }

    /**
     * Whether a reason is needed, and which moves are allowed, is decided by TeamService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TeamStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
