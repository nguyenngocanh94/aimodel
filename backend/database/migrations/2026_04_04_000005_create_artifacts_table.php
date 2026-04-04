<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('node_id', 255);
            $table->string('name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->string('disk', 20)->default('local');
            $table->string('path', 500);
            $table->timestamp('created_at')->nullable();

            $table->foreign('run_id')
                ->references('id')
                ->on('execution_runs')
                ->cascadeOnDelete();

            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
