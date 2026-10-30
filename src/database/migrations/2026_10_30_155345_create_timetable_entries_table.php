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
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();                         // id: 連番・主キー
            $table->unsignedTinyInteger('day');   // 曜日(1=月〜5=金) NOT NULL
            $table->integer('period');            // 時限(1〜6) NOT NULL
            $table->string('title');              // 授業名 NOT NULL
            $table->string('room')->nullable();   // 教室名 NULL許容
            $table->timestamps();                 // created_at / updated_at

            $table->unique(['day', 'period']);    // 同じ曜日・時限の重複を禁止
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
