<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Public JSON shape of a chat log; hides user_id and raw column names.
class ChatLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->id,
            'status' => $this->status,
            'provider' => $this->provider,
            'model_used' => $this->model,
            'think' => $this->think,
            'theme' => $this->theme,
            'prompt' => $this->prompt,
            'ai_response' => $this->response,
            'tokens' => [
                'prompt' => $this->prompt_tokens,
                'completion' => $this->completion_tokens,
                'total' => $this->total_tokens,
            ],
            'timestamp' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
