<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 06 §1. registration_robot joins when robots exist (phase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->index()->constrained()->restrictOnDelete();
            $table->string('slug', 140)->unique();
            $table->string('title_en', 160);
            $table->string('title_ar', 160)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->longText('rules_en')->nullable();
            $table->longText('rules_ar')->nullable();
            $table->longText('terms_en')->nullable();
            $table->longText('terms_ar')->nullable();
            $table->string('venue', 200)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('timezone', 40)->default('Africa/Tripoli');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('status', 16);
            $table->string('visibility', 16);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamp('results_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name_en', 120);
            $table->string('name_ar', 120)->nullable();
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->unsignedTinyInteger('members_min')->default(1);
            $table->unsignedTinyInteger('members_max')->default(10);
            $table->unsignedTinyInteger('robots_per_team')->default(1);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedInteger('max_weight_g')->nullable();
            $table->unsignedInteger('max_length_mm')->nullable();
            $table->unsignedInteger('max_width_mm')->nullable();
            $table->unsignedInteger('max_height_mm')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'team_id']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('event_categories')->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->string('status', 16);
            $table->string('active_key', 40)
                ->storedAs("case when status in ('invited','pending','approved','waitlisted') then concat(category_id, '-', team_id) else null end")
                ->nullable()
                ->unique();
            $table->json('members_snapshot')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->string('override_reason', 1000)->nullable();
            $table->json('override_violations')->nullable();
            $table->unsignedSmallInteger('waitlist_position')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::create('registration_members', function (Blueprint $table) {
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['registration_id', 'user_id']);
        });

        Schema::create('event_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained('event_registrations')->cascadeOnDelete();
            $table->unsignedSmallInteger('placement')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->string('award_en', 120)->nullable();
            $table->string('award_ar', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_results');
        Schema::dropIfExists('registration_members');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_invitations');
        Schema::dropIfExists('event_categories');
        Schema::dropIfExists('events');
    }
};
