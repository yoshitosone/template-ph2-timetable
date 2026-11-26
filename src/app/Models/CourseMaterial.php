<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseMaterial extends Model
{
    protected $fillable = [
        'course_id',
        'path',
        'original_name',
    ];

    protected $casts = [
        'retired_at' => 'datetime',
    ];

    // アップロードできるファイルの規則（アップロードと差し替えで共通。10MB = 10240KB）
    public const MAX_KB = 10240;

    public static function uploadRules(): array
    {
        return ['required', 'mimes:pdf', 'max:' . self::MAX_KB];
    }

    public static function uploadMessages(): array
    {
        return [
            'file.required' => 'ファイルを選んでください',
            'file.mimes' => 'PDF ファイルを選んでください',
            'file.max' => 'ファイルは10MB以内にしてください',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
