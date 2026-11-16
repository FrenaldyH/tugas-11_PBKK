<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the prompt sent to /api/chat-ai and /api/chat-ai/stream.
class ChatAiRequest extends FormRequest
{
    // Route is already behind auth:sanctum, so any token holder may ask.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:1000'],
            'theme' => ['sometimes', 'string', Rule::in(array_keys(config('llm.themes')))],
            'think' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Prompt wajib diisi.',
            'prompt.string' => 'Prompt harus berupa teks.',
            'prompt.max' => 'Prompt maksimal 1000 karakter.',
            'theme.in' => 'Tema tidak dikenal. Pilihan: '.implode(', ', array_keys(config('llm.themes'))).'.',
            'think.boolean' => 'Mode berpikir harus true atau false.',
        ];
    }

    // Theme with default applied.
    public function theme(): string
    {
        return $this->input('theme', 'default');
    }

    // Reasoning mode for this request; falls back to LLM_THINK when not sent.
    public function think(): bool
    {
        return $this->boolean('think', (bool) config('llm.think'));
    }
}
