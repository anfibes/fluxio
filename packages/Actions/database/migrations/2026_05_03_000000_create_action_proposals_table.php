<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intent');
            $table->string('status', 50)->default('draft');
            $table->decimal('confidence', 3, 2)->default(0.0);
            $table->text('source_text');
            $table->json('entities')->nullable();
            $table->json('missing')->nullable();
            $table->json('warnings')->nullable();
            $table->json('editable_fields')->nullable();
            $table->json('changes')->nullable();
            $table->boolean('needs_confirmation')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_proposals');
    }
};
