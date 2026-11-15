<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 07 §2: team funding requests and expenses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('purpose', 1000);
            $table->json('breakdown')->nullable();
            $table->foreignId('source_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->bigInteger('approved_minor')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->foreignId('budget_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vendor_name', 160)->nullable();
            $table->date('date');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->decimal('rate_to_lyd', 18, 8)->default(1);
            $table->json('items');
            $table->string('status', 16)->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('funding_requests');
    }
};
