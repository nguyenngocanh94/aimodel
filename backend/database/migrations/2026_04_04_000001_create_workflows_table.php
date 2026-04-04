<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('schema_version')->default(1);
            $table->jsonb('tags')->default('[]');
            $table->jsonb('document');
            $table->timestamps();

            $table->index('name');
            $table->index('updated_at');
        });

        // GIN index on tags for fast JSONB containment queries (PostgreSQL only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX workflows_tags_gin ON workflows USING GIN (tags)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
