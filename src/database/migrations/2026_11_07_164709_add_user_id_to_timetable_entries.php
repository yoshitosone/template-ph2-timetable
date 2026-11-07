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
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique(['course_id']);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['course_id']);
        });
    }
};
