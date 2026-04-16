<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('run_id');
            $table->string('node_id');
            $table->string('channel');  // telegram, ui, mcp
            $table->string('channel_message_id')->nullable();  // Telegram message_id
            $table->string('chat_id')->nullable();  // Telegram chat_id
            $table->string('status')->default('waiting');  // waiting, responded, expired
            $table->json('proposal_payload')->nullable();  // what was sent to human
            $table->json('response_payload')->nullable();  // what human replied
            $table->json('node_state')->nullable();  // serialized node state for handleResponse
            $table->timestamps();
            $table->timestamp('responded_at')->nullable();

            $table->index('channel_message_id');
            $table->index(['chat_id', 'status']);
            $table->index(['run_id', 'node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_interactions');
    }
};
