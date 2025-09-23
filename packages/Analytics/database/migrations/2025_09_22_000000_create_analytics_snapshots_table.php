<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->index(['snapshot_date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
