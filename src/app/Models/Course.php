<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    public const SPRING = 'spring';
    public const AUTUMN = 'autumn';

    // 現在の月から、今学期を決める
    public static function currentSemester(): string
    {
        return in_array(now()->month, config('credits.spring_months'))
            ? self::SPRING
            : self::AUTUMN;
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function entries()
    {
        return $this->hasMany(TimetableEntry::class);
    }
    public $timestamps = false;

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'timetable_entries', 'course_id', 'user_id')
            ->wherePivot('status', TimetableEntry::APPROVED);  // 承認済みの申請のみ
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'course_id', 'user_id');
    }
}
