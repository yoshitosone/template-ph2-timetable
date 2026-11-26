<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMaterialController extends Controller
{
    // 資料のアップロード（教務課だけ。ルートの can:admin で守る）
    public function store(Request $request, Course $course)
    {
        $request->validate(['file' => CourseMaterial::uploadRules()], CourseMaterial::uploadMessages());

        $file = $request->file('file');

        // 非公開の保存先（local）に、サーバー側で作った名前で保存する（R1）
        $path = $file->store('materials');

        $course->materials()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),   // アップロード時のファイル名を返すメソッド
        ]);

        return redirect('/courses/' . $course->id);
    }

    // 差し替え：新しいファイルを別の行として作り、古い行は配布をやめる
    public function replace(Request $request, CourseMaterial $material)
    {
        $request->validate(['file' => CourseMaterial::uploadRules()], CourseMaterial::uploadMessages());

        $file = $request->file('file');

        // 新しい行の作成と、古い行の配布停止は、片方だけ成功しないようまとめる（R4）
        DB::transaction(function () use ($file, $material) {
            $material->course->materials()->create([
                'path' => $file->store('materials'),
                'original_name' => $file->getClientOriginalName(),
            ]);

            $material->retired_at = now();   // 配布をやめた日時（今）
            $material->save();
        });

        return redirect('/courses/' . $material->course_id);
    }

    // 削除：ファイルは消さず、配布をやめるだけ（緊急時に戻せるようにするため）
    public function destroy(CourseMaterial $material)
    {
        // すでに配布をやめていれば、日時を上書きしない
        if ($material->retired_at === null) {
            $material->retired_at = now();
            $material->save();
        }

        return redirect('/courses/' . $material->course_id);
    }
}
