<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_proposals', function (Blueprint $table): void {
            // Server-owned entity identity map (identity continuity, slice 1).
            // null  = legacy proposal built before this contract existed;
            // []    = new proposal, no resolved identity yet;
            // map   = {key: {id, type, label}} written only by entity resolution.
            $table->json('resolved_entities')->nullable()->after('ambiguities');
        });
    }

    public function down(): void
    {
        Schema::table('action_proposals', function (Blueprint $table): void {
            $table->dropColumn('resolved_entities');
        });
    }
};
