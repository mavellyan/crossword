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
        Schema::table('crossword_clues', function (Blueprint $table) {
            $table->dropColumn('intersection_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crossword_clues', function (Blueprint $table) {
            $table->integer('intersection_index')->nullable()->after('direction');
        });
    }
};
