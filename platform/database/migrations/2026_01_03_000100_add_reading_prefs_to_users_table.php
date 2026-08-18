<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-reader typography.
     *
     * On a platform whose entire proposition is uninterrupted reading, the
     * reader — not the designer — should decide what is comfortable. Stored on
     * the user so it follows them across devices rather than living in one
     * browser's localStorage.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('reading_prefs')->nullable()->after('is_premium');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reading_prefs');
        });
    }
};
