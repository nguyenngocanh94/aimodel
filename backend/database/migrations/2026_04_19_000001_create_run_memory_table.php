<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_memory', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('workflow_id')->nullable();
            $table->string('scope');
            $table->string('key');
            $table->jsonb('value');
            $table->jsonb('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('scope');
            $table->index('key');
            $table->index('expires_at');
            $table->unique(['scope', 'key']);

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_memory');
    }
};
