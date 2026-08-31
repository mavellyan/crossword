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
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'abandoned'])->default('not_started')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crossword_attempts', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress')->change();
        });
    }
};
