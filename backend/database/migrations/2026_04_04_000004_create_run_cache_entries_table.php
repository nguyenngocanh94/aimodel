<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_cache_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cache_key', 255)->unique();
            $table->string('node_type', 50);
            $table->string('template_version', 20);
            $table->jsonb('output_payloads');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();

            $table->index('node_type');
            $table->index('last_accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_cache_entries');
    }
};
