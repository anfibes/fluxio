<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            $table->json('last_refinement')->nullable()->after('execution_result');
        });
    }

    public function down(): void
    {
        Schema::table('action_proposals', function (Blueprint $table) {
            $table->dropColumn('last_refinement');
        });
    }
};
