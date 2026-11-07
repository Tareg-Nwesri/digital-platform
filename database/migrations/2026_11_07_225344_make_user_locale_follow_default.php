<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RTL first: a user's language is null until they choose one, so they
 * follow the cookie and then the platform default (Arabic). The old column
 * defaulted to 'en', which pinned every account to English; before launch
 * nobody chose English on purpose, so those values are cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->nullable()->default(null)->change();
        });

        DB::table('users')->where('locale', 'en')->update(['locale' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('locale')->update(['locale' => 'en']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->change();
        });
    }
};
