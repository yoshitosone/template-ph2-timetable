<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {        
        $q = $request->query('q');
        $courses = Course::with([
            'entries' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
        ->when($q, function ($query) use ($q) {
            $query->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('teacher', 'like', '%' . $q . '%');
            });
        })
        ->orderBy('day')->orderBy('period')->get();

        return view('courses.index', ['courses' => $courses, 'q' => $q]);
    }

    public function show(Course $course)
    {
        $reviews = $course->reviews()->with('user')->latest()->get();
        return view('courses.show', ['course' => $course, 'reviews' => $reviews]);
    }

    public function storeReview(Request $request, Course $course)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ],[
            'body.required' => 'レビューを入力してください',
            'body.max' => 'レビューは1000文字以内で入力してください',
        ]);

        $course->reviews()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect('/courses/' . $course->id);
    }
}
