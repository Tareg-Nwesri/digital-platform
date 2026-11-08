<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 07 §1: the ledger core. Sponsors, receipts, payouts and the other
 * documents that post through it follow in their own migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24)->index();
            $table->nullableMorphs('owner');
            $table->string('name_en', 160);
            $table->string('name_ar', 160)->nullable();
            $table->char('currency', 3);
            $table->boolean('is_restricted')->default(false);
            $table->string('purpose', 500)->nullable();
            $table->bigInteger('balance_minor')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->unique(['type', 'owner_type', 'owner_id', 'currency', 'name_en'], 'finance_accounts_identity_unique');
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->date('starts_on')->unique();
            $table->date('ends_on')->unique();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('prefix', 4);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->primary(['prefix', 'year']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->date('date')->index();
            $table->foreignId('fiscal_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('from_account_id')->index()->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->index()->constrained('finance_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->decimal('rate_to_lyd', 18, 8)->default(1);
            $table->string('type', 24);
            $table->string('status', 16);
            $table->string('description', 500);
            $table->string('reference', 120)->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('override_reason', 500)->nullable();
            foreach (['created_by', 'submitted_by', 'approved_by', 'rejected_by'] as $user) {
                $table->foreignId($user)->nullable()->constrained('users')->restrictOnDelete();
            }
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('finance_accounts');
    }
};
