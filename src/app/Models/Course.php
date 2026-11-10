<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    //
    public function entries()
    {
        return $this->hasMany(TimetableEntry::class);
    }
    public $timestamps = false;

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
