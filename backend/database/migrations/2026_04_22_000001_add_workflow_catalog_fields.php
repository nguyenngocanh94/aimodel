<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the catalog-fields that the ComposeWorkflow/PersistWorkflow/RunWorkflow/
 * ListWorkflows toolchain was written against but which were never migrated.
 *
 * Without these columns every TelegramAgent turn throws:
 *   Call to undefined method App\Models\Workflow::triggerable()
 * because App\Services\TelegramAgent\TelegramAgent::instructions() calls
 * Workflow::triggerable() to build the catalog, and PersistWorkflowTool tries
 * to create() rows with slug / triggerable / nl_description / param_schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->after('name');
            $table->boolean('triggerable')->default(false)->after('slug');
            $table->text('nl_description')->nullable()->after('description');
            $table->jsonb('param_schema')->nullable()->after('nl_description');

            $table->unique('slug');
            $table->index('triggerable');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['triggerable']);
            $table->dropColumn(['slug', 'triggerable', 'nl_description', 'param_schema']);
        });
    }
};
