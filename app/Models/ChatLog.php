<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    protected $fillable = [
        'theme', 'provider', 'model', 'think', 'prompt', 'response', 'status',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
    ];

    // SQLite/MySQL store booleans as 0/1; expose them as true/false
    protected function casts(): array
    {
        return ['think' => 'boolean'];
    }

    // Owner of this chat log
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
