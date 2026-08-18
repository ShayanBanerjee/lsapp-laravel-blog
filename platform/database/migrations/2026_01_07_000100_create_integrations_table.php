<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Per-user credentials for outside services.
         *
         * The token is encrypted at rest via an Eloquent cast rather than
         * stored raw: a read-only leak of this table (a stray backup, a log of
         * a query) would otherwise hand over live write access to someone's
         * Readwise or Notion account, which is a far worse breach than
         * anything in our own data.
         */
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('service');              // readwise | notion | …
            $table->text('token')->nullable();      // encrypted
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            // One connection per service per user.
            $table->unique(['user_id', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
