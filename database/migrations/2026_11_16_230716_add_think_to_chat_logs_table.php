<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    // Record whether the model's reasoning ("thinking") mode was on for each chat
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->boolean('think')->default(false)->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->dropColumn('think');
        });
    }
};
