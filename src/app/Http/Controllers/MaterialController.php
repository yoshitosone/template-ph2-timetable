<?php

namespace App\Http\Controllers;

use App\Models\CourseMaterial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    // ダウンロード（保存先は非公開なので、必ずここを通る）
    public function download(CourseMaterial $material)
    {
        Gate::authorize('download', $material);

        return Storage::download($material->path, $material->original_name);
    }

    // プレビュー（ダウンロードせずに、ブラウザで PDF を開く）
    public function preview(CourseMaterial $material)
    {
        Gate::authorize('download', $material);

        return Storage::response($material->path, $material->original_name);
    }
}
