<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 05 §1: the shared catalogue. Items are archived, never deleted, so
 * bill-of-materials lines (phase 3) stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->string('name_en', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('slug', 120)->unique();
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->string('name', 160);
            $table->string('part_number', 80)->nullable()->index();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->json('specs')->nullable();
            $this->reviewColumns($table, 'parts');
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('website')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $this->reviewColumns($table, 'vendors');
            $table->timestamps();
        });

        Schema::create('vendor_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price_minor');
            $table->char('currency', 3)->default('LYD');
            $table->string('url', 500)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->date('checked_at')->nullable();
            $this->reviewColumns($table, null);
            $table->timestamps();
            $table->index(['part_id', 'vendor_id', 'currency']);
        });
    }

    private function reviewColumns(Blueprint $table, ?string $mergeTable): void
    {
        $table->string('status', 16)->index();
        $table->foreignId('suggested_by_team_id')->nullable()->constrained('teams')->nullOnDelete();
        $table->foreignId('suggested_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->string('review_note', 500)->nullable();

        if ($mergeTable !== null) {
            $table->foreignId('merged_into_id')->nullable()->constrained($mergeTable)->nullOnDelete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_offers');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('part_categories');
    }
};
