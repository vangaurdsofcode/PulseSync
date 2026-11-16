<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'uuid'],
            'type' => ['required', 'in:page_view,lead'],
            'occurred_at' => ['required', 'date'],
            'payload' => ['required', 'array'],
            'payload.page_url' => ['required', 'url', 'max:2048'],
            'payload.browser' => ['nullable', 'string', 'max:120'],
            'payload.fields' => ['sometimes', 'array', 'max:40'],
            'metadata' => ['sometimes', 'array', 'max:40'],
        ];
    }
}
