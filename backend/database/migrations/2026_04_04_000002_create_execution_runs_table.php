<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->string('mode', 20)->default('live');
            $table->string('trigger', 20);
            $table->string('target_node_id', 255)->nullable();
            $table->jsonb('planned_node_ids')->default('[]');
            $table->string('status', 20)->default('pending');
            $table->jsonb('document_snapshot')->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->jsonb('node_config_hashes')->default('{}');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('termination_reason', 30)->nullable();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->cascadeOnDelete();

            $table->index('workflow_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_runs');
    }
};
