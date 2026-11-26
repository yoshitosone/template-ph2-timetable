<?php

namespace App\Policies;

use App\Models\CourseMaterial;
use App\Models\User;

class CourseMaterialPolicy
{
    // ダウンロード・プレビューできるか
    public function download(User $user, CourseMaterial $material): bool
    {
        // 教務課は、配布をやめた資料も含めて、すべて見られる
        if ($user->is_admin) {
            return true;
        }

        // 配布をやめた資料は、学生には配らない
        if ($material->retired_at !== null) {
            return false;
        }

        // この授業を承認済みで履修している学生だけ（Course::users は承認済みだけを返す関係）
        return $material->course->users->contains('id', $user->id);
    }
}
