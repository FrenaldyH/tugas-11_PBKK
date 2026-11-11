<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    protected $fillable = [
        'theme', 'provider', 'model', 'prompt', 'response', 'status',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
    ];

    // Owner of this chat log
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
