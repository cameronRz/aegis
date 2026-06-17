<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => [
                'required',
                'integer',
                Rule::exists('ai_conversations', 'id')->where('user_id', $this->user()->id),
            ],
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
