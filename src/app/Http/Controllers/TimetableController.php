<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    public function index()
    {
        // 区間1：月曜日の授業（時限の早い順）[cite: 1]
        $mondayCourses = DB::select('SELECT period, name, teacher, room FROM courses WHERE day = ? ORDER BY period;', [1]);

        // 区間2：必修科目（科目コードの順）[cite: 1]
        $requiredCourses = DB::select('SELECT name, credits FROM courses WHERE required = true ORDER BY code;');

        return view('timetable', [
            'title' => 'POSSE大学 時間割',
            'mondayCourses' => $mondayCourses,
            'requiredCourses' => $requiredCourses,
        ]);
    }
}