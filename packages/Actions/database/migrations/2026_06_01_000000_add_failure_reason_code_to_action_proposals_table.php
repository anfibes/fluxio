<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            // Closed ExecutionFailureReason code (e.g. 'unsupported_intent' |
            // 'execution_failed'). Persisted alongside the sanitized failure_reason
            // message so the typed failure can be exposed without parsing prose.
            $table->string('failure_reason_code')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            $table->dropColumn('failure_reason_code');
        });
    }
};
