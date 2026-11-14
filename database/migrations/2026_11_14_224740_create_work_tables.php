<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 04 §1: projects, robots and their bill of materials, plus the robots
 * entered with an event registration (LLD 06 §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 140)->unique();
            $table->string('title_en', 160);
            $table->string('title_ar', 160)->nullable();
            $table->string('excerpt_en', 400)->nullable();
            $table->string('excerpt_ar', 400)->nullable();
            $table->longText('body_en')->nullable();
            $table->longText('body_ar')->nullable();
            $table->string('status', 16);
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['team_id', 'status']);
        });

        Schema::create('robots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('status', 16);
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['team_id', 'status']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('registration_robot', function (Blueprint $table) {
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('robot_id')->constrained()->restrictOnDelete();
            $table->primary(['registration_id', 'robot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_robot');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('robots');
        Schema::dropIfExists('projects');
    }
};
