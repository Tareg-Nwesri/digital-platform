<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LLD 08 §3: every activity entry records the workspace it happened in and,
 * while someone acts as another user, who that really was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->index()->after('properties');
            $table->unsignedBigInteger('impersonator_id')->nullable()->index()->after('workspace_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['workspace_id', 'impersonator_id']);
        });
    }
};
