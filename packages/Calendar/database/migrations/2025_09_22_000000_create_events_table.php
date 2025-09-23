<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            // placeholder per ricorrenze future (RFC5545)
            $table->string('rrule')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
