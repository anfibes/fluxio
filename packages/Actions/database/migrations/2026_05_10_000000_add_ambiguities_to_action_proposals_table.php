<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_proposals', function (Blueprint $table): void {
            $table->json('ambiguities')->nullable()->after('last_refinement');
        });
    }

    public function down(): void
    {
        Schema::table('action_proposals', function (Blueprint $table): void {
            $table->dropColumn('ambiguities');
        });
    }
};
