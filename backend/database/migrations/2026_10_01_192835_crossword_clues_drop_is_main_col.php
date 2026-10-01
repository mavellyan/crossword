<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the unused legacy main-entry indicator.
     */
    public function up(): void
    {
        Schema::table('crossword_clues', function (Blueprint $table): void {
            $table->dropColumn('is_main');
        });
    }

    /**
     * Restore the legacy main-entry indicator.
     */
    public function down(): void
    {
        Schema::table('crossword_clues', function (Blueprint $table): void {
            $table->boolean('is_main')->default(false);
        });
    }
};