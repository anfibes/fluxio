<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            $table->json('execution_result')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            $table->dropColumn('execution_result');
        });
    }
};
