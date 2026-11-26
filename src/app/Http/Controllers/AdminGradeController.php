<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Grade;
use Illuminate\Http\Request;

class AdminGradeController extends Controller
{
    // 成績 CSV の取り込み（教務課だけ。ルートの can:admin で守る）
    public function store(Request $request, Course $course)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'csv_file.required' => 'ファイルを選んでください',
            'csv_file.mimes' => 'CSV ファイルを選んでください',
            'csv_file.max' => 'ファイルは1MB以内にしてください',
        ]);

        $rows = $this->readRows($request->file('csv_file')->getRealPath());

        // UTF-8 でないファイル（Excel の普通の「CSV」は Shift_JIS）は、原因が分かる案内を出す
        if ($rows === null) {
            return back()->withErrors(['csv' => ['ファイルの文字コードが UTF-8 ではありません。Excel で「CSV UTF-8（コンマ区切り）」を選んで保存し直してください']]);
        }

        // 見出し行の検査（R4）。違うファイルは、中身を見る前に全体を誤りにする
        if (($rows[0] ?? null) !== ['氏名', 'メールアドレス', '成績']) {
            return back()->withErrors(['csv' => ['1行目：見出しは「氏名,メールアドレス,成績」にしてください']]);
        }

        // 承認済みで履修している学生を、メールアドレス（小文字）で引けるようにする
        $students = $course->users()->get()->keyBy(fn ($user) => mb_strtolower($user->email));

        $errors = [];   // 画面に出す、誤りの一覧
        $seen = [];     // すでに出てきたメールアドレス => 何行目か（R2）
        $valid = [];    // 登録する [学生, 成績]

        foreach ($rows as $index => $row) {
            if ($index === 0 || $row === []) {
                continue;   // 見出し行と、まったくの空行は飛ばす
            }

            $line = $index + 1;   // 見出し行を1行目として数える

            if (count($row) !== 3) {
                $errors[] = "{$line}行目：列の数が合いません";
                continue;
            }

            // 前後の空白を取る（R5）
            [$name, $email, $grade] = array_map('trim', $row);
            $key = mb_strtolower($email);

            if ($email === '') {
                $errors[] = "{$line}行目：メールアドレスが空です";
            } elseif (isset($seen[$key])) {
                $errors[] = "{$line}行目：メールアドレスが{$seen[$key]}行目と重複しています";
            } elseif (! $students->has($key)) {   // 履修者に、そのメールがあるか
                $errors[] = "{$line}行目：履修していない学生です（{$email}）";
            } elseif ($grade === '') {
                $errors[] = "{$line}行目：成績が空欄です";
            } elseif (! in_array($grade, Grade::GRADES, true)) {   // 成績として使える文字の一覧
                $errors[] = "{$line}行目：成績は A〜F のいずれかにしてください";
            } else {
                $valid[] = [$students[$key], $grade];
            }

            if ($email !== '' && ! isset($seen[$key])) {
                $seen[$key] = $line;   // このメールが出てきた行（上で作った行番号の変数）
            }
        }

        // 誤りが1つでもあれば、1行も登録しない
        if ($errors !== []) {
            return back()->withErrors(['csv' => $errors]);
        }

        // すでに成績があれば上書き、なければ新しく作る。1回の命令でまとめて行う（C2）
        $now = now();
        $records = array_map(fn ($pair) => [
            'user_id' => $pair[0]->id,
            'course_id' => $course->id,
            'grade' => $pair[1],
            'created_at' => $now,
            'updated_at' => $now,
        ], $valid);

        Grade::upsert($records, ['user_id', 'course_id'], ['grade', 'updated_at']);

        return redirect('/courses/' . $course->id)->with('status', count($valid) . '件の成績を登録しました');
    }

    // CSV を行ごとの配列にする（空行は [] にして、行番号がずれないようにする）
    private function readRows(string $path): ?array
    {
        $content = file_get_contents($path);

        // UTF-8 として正しくない文字が混ざっていれば、読めないとして null を返す
        if (! mb_check_encoding($content, 'UTF-8')) {
            return null;
        }

        // 先頭の BOM を取り除く（R3）
        if (str_starts_with($content, "\xEF\xBB\xBF")) {   // UTF-8 の BOM（EF BB BF）
            $content = substr($content, 3);     // BOM の長さ（バイト数）
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row === [null] ? [] : array_map(fn ($cell) => (string) $cell, $row);
        }
        fclose($stream);

        return $rows;
    }
}
