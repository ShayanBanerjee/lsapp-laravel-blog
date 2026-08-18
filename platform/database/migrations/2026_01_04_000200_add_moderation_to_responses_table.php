<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            // Nullable: the overwhelming majority of responses are never
            // touched by moderation and should cost nothing to store.
            $table->string('flagged_category')->nullable()->after('body');
            $table->timestamp('flagged_at')->nullable()->after('flagged_category');

            $table->index('flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(['flagged_category', 'flagged_at']);
        });
    }
};
