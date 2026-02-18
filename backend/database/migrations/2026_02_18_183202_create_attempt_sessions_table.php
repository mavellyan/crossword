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
        Schema::create('attempt_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crossword_attempt_id')->constrained()->onDelete('cascade');
            $table->timestamp('session_start')->useCurrent();
            $table->timestamp('session_end')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_sessions');
    }
};
