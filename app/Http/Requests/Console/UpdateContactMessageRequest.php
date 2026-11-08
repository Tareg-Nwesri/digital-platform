<?php

namespace App\Http\Requests\Console;

use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Change a message's status or assignee. The assignee must be able to read
 * the message.
 */
class UpdateContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('message'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(MessageStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $assigneeId = $this->input('assigned_to');

                if (! filled($assigneeId) || $validator->errors()->has('assigned_to')) {
                    return;
                }

                /** @var ContactMessage $message */
                $message = $this->route('message');
                $assignee = User::query()->find((int) $assigneeId);

                if ($assignee === null || ! $assignee->can('view', $message)) {
                    $validator->errors()->add('assigned_to', __('content.errors.assignee_cannot_view'));
                }
            },
        ];
    }
}
