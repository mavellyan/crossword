<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crossword_attempts', function (Blueprint $table) {
            $table->unsignedInteger('elapsed_time')->default(0)->after('state_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crossword_attempts', function (Blueprint $table) {
            $table->dropColumn('elapsed_time');
        });
    }
};
