<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_run_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('node_id', 255);
            $table->string('status', 20)->default('pending');
            $table->string('skip_reason', 30)->nullable();
            $table->jsonb('blocked_by_node_ids')->nullable();
            $table->jsonb('input_payloads')->default('{}');
            $table->jsonb('output_payloads')->default('{}');
            $table->text('error_message')->nullable();
            $table->boolean('used_cache')->default(false);
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('run_id')
                ->references('id')
                ->on('execution_runs')
                ->cascadeOnDelete();

            $table->unique(['run_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_run_records');
    }
};
