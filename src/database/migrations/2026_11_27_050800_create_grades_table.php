<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 1);   // A・B・C・D・F
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);   // 1人の1授業に、成績は1つ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
