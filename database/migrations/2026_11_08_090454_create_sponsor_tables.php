<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 07 §1: sponsors, their sponsorships and split rules, and receipts.
 * Logos, agreements and proofs (media) follow with private file serving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 160);
            $table->string('name_ar', 160)->nullable();
            $table->string('type', 16);
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('public_consent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sponsor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 190)->nullable();
            $table->timestamps();
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('kind', 12);
            $table->bigInteger('pledged_minor')->default(0);
            $table->char('currency', 3)->default('LYD');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('purpose')->nullable();
            $table->text('restrictions')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->string('status', 16);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('split_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->decimal('percent', 5, 2)->nullable();
            $table->bigInteger('fixed_minor')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE split_rules ADD CONSTRAINT split_rules_one_kind CHECK ((percent IS NULL) <> (fixed_minor IS NULL))');

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('sponsorship_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->foreignId('payer_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->date('date');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->decimal('rate_to_lyd', 18, 8)->default(1);
            $table->string('method', 12);
            $table->string('reference', 120)->nullable();
            $table->json('in_kind_items')->nullable();
            $table->string('status', 16)->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('split_rules');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsor_contacts');
        Schema::dropIfExists('sponsors');
    }
};
