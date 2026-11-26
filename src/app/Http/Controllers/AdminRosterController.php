<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\CsvCell;

class AdminRosterController extends Controller
{
    // 履修者名簿の CSV ダウンロード（教務課だけ。ルートの can:admin で守る）
    public function download(Course $course)
    {
        // Course::users は、承認済みの学生だけを返す関係
        $students = $course->users()->orderBy('email')->get();   // メールアドレス順

        return response()->streamDownload(function () use ($students) {   // CSV をその場で作って送るメソッド
            $out = fopen('php://output', 'w');

            // Excel で文字化けしないよう、UTF-8 の BOM（EF BB BF）を先頭に書く
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['氏名', 'メールアドレス', '成績']);
            foreach ($students as $student) {
                fputcsv($out, [CsvCell::safe($student->name), CsvCell::safe($student->email), '']);   // 1行を CSV で書く関数
            }

            fclose($out);
        }, $course->code . '_名簿.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
