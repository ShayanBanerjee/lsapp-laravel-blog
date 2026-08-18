<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Source of truth for premium access, so comps and grants never require
        // a fake subscription record.
        Schema::create('theme_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('subscription');  // subscription | grant
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'universe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_entitlements');
    }
};
