<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (provider, provider account) the user has linked.
     *
     * A separate table rather than `google_id` / `facebook_id` columns on
     * users: adding a seventh provider should not be a migration on the users
     * table, and one account legitimately links several providers.
     */
    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');              // google | facebook | github
            $table->string('provider_id');           // the provider's stable user id
            $table->string('email')->nullable();     // as reported by the provider
            $table->string('nickname')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // The lookup on every social login, and the guarantee that one
            // provider account cannot be attached to two users.
            $table->unique(['provider', 'provider_id']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_identities');
    }
};
