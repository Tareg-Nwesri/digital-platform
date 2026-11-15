<?php

namespace App\Http\Requests\Console;

use App\Domain\Events\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()->can('update', $event);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480', 'extensions:'.implode(',', Event::DOCUMENT_EXTENSIONS), 'mimes:'.implode(',', Event::DOCUMENT_EXTENSIONS)],
            'title' => ['nullable', 'string', 'max:160'],
        ];
    }
}
