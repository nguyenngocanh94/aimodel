<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('name');
            $table->boolean('triggerable')->default(false)->after('slug');
            $table->text('nl_description')->nullable()->after('triggerable');
            $table->jsonb('param_schema')->nullable()->after('nl_description');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'triggerable', 'nl_description', 'param_schema']);
        });
    }
};
