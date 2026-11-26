<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    // 成績として使える文字
    public const GRADES = ['A', 'B', 'C', 'D', 'F'];

    protected $fillable = [
        'user_id',
        'course_id',
        'grade',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
