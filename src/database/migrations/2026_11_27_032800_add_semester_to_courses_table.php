<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 春学期にする授業（残りは秋学期）
    private array $springCodes = [
        'CS101', 'EC100', 'EN101', 'MA110', 'DB210', 'LA150', 'HI130', 'NW220', 'PS100', 'PE100',
        'PS200', 'MK210', 'LI140', 'CN100', 'AL230', 'SE240', 'CA200', 'AI260', 'LW110', 'ART100',
    ];

    public function up(): void
    {
        // 既存の行に値を入れるため、いったん NULL を許して列を足す
        Schema::table('courses', function (Blueprint $table) {
            $table->string('semester')->nullable();
        });

        DB::table('courses')->update(['semester' => 'autumn']);
        DB::table('courses')->whereIn('code', $this->springCodes)->update(['semester' => 'spring']);

        // 全部の行に値が入ったので、NULL を許さないようにする
        Schema::table('courses', function (Blueprint $table) {
            $table->string('semester')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('semester');
        });
    }
};
