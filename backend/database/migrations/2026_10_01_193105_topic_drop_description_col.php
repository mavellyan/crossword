<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the unused topic description.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    /**
     * Restore the topic description.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
    }
};