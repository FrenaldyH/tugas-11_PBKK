<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // Chat history: one row per prompt sent to the LLM (success or failure).
    public function up(): void
    {
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('theme', 50)->default('default');
            $table->string('provider', 30)->nullable(); // null when every provider failed
            $table->string('model', 100)->nullable();
            $table->text('prompt');
            $table->longText('response')->nullable();
            $table->string('status', 10); // success | failed
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->timestamps();

            // History endpoint lists a user's logs newest first
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
