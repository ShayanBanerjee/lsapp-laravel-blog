<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reader → writer money.
 *
 * Every amount is an integer in minor units with an explicit currency. There
 * is no float column anywhere in this schema, and no computed "percentage"
 * column either — the split is stored as the two amounts it actually resolved
 * to, so a later change to the platform rate cannot retroactively rewrite what
 * a writer was told they earned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('provider', 30)->default('stripe');
            $table->string('provider_account_id')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->default('GBP');

            // Set only when the provider confirms the account can receive
            // money. Until then the writer can be paid *into* the platform but
            // nothing can be paid out, and the UI must say so.
            $table->timestamp('payouts_enabled_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->id();

            // Nullable: a reader may support a writer without an account here.
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            // Attribution follows the persona, as everywhere else.
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();

            $table->string('kind', 20);
            $table->string('currency', 3)->default('GBP');

            // The three amounts, stored rather than derived. gross = fee + net,
            // enforced in RevenueSplit and asserted in tests.
            $table->unsignedInteger('gross_minor');
            $table->unsignedInteger('platform_fee_minor');
            $table->unsignedInteger('writer_net_minor');
            $table->unsignedInteger('rate_basis_points');

            $table->string('status', 20)->default('pending');
            $table->string('provider', 30)->default('stripe');

            // The provider's own id for the charge. Unique so a webhook
            // delivered twice — which every processor does — cannot credit a
            // writer twice.
            $table->string('provider_ref')->nullable()->unique();

            $table->string('message', 500)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'status', 'settled_at']);
            $table->index(['post_id', 'status']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('GBP');
            $table->string('status', 20)->default('active');
            $table->string('provider_ref')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();

            // One membership per reader per persona.
            $table->unique(['user_id', 'persona_id']);
            $table->index(['to_user_id', 'status']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('GBP');
            $table->string('status', 20)->default('pending');
            $table->string('provider_ref')->nullable();

            // The window of contributions this payout settles, so a writer can
            // reconcile a transfer against the earnings that produced it.
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('payout_accounts');
    }
};
