<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);

            /*
             * API tokens, encrypted at rest by the model's `encrypted:array`
             * cast rather than stored raw.
             *
             * These are third-party credentials belonging to the user, and a
             * database dump that leaks them hands an attacker the user's Ghost
             * blog and Notion workspace, not just their account here. Text so
             * the ciphertext is not truncated by a column length.
             */
            $table->text('credentials');
            $table->json('settings')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
