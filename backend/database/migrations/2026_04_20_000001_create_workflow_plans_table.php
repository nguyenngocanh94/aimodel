<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores historical WorkflowPlanner outputs so the PriorPlanRetrievalTool
 * can surface similar past plans. See LK-F2 in
 * docs/plans/2026-04-19-laravel-ai-capabilities.md.
 *
 * The embedding column is added by a follow-up migration (LK-G1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('brief');
            // SHA-256 of the normalized brief text — used for dedup + fast lookup.
            $table->string('brief_hash', 64)->index();
            $table->jsonb('plan');
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_plans');
    }
};
