<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The 16-digit ORCID iD, stored in its canonical hyphenated form
            // (0000-0002-1825-0097). Unique because an ORCID identifies one
            // researcher — two accounts claiming the same one is the exact
            // thing the identifier exists to prevent.
            $table->string('orcid_id', 19)->nullable()->unique()->after('email');
            $table->string('orcid_name')->nullable()->after('orcid_id');
            $table->timestamp('orcid_linked_at')->nullable()->after('orcid_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['orcid_id', 'orcid_name', 'orcid_linked_at']);
        });
    }
};
