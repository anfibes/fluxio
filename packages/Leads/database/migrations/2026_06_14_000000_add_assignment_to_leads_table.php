<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable()->after('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn(['assigned_to_user_id', 'assigned_at']);
        });
    }
};
