<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 07 §1: payouts to people (bonuses, stipends, prize shares,
 * reimbursements). Proof files follow with private media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->restrictOnDelete();
            $table->string('guardian_name', 160)->nullable();
            $table->string('guardian_contact', 160)->nullable();
            $table->string('type', 16);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->decimal('rate_to_lyd', 18, 8)->default(1);
            $table->foreignId('source_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->string('reason', 500);
            $table->nullableMorphs('link');
            $table->string('status', 16)->index();
            $table->string('method', 12)->nullable();
            $table->string('reference', 120)->nullable();
            $table->date('paid_on')->nullable();
            $table->boolean('needs_second_approval')->default(false);
            $table->string('rejection_reason', 500)->nullable();
            foreach (['created_by', 'submitted_by', 'approved_by', 'second_approved_by', 'paid_by'] as $user) {
                $table->foreignId($user)->nullable()->constrained('users')->nullOnDelete();
            }
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
