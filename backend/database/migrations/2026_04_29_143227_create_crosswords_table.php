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
        Schema::create('crosswords', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('main_solution');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crosswords');
    }
};
