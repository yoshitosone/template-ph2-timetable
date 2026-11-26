<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $tag = $request->query('tag');

        $courses = Course::with([
            'tags',
            'entries' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            },
            'favoritedBy' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            },
        ])
        ->withCount('favoritedBy')
        ->withCount('users')
        ->when($q, function ($query) use ($q) {
            $query->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('teacher', 'like', '%' . $q . '%');
            });
        })
        ->when($tag, function ($query) use ($tag) {
            $query->whereHas('tags', function ($query) use ($tag) {
                $query->where('tags.id', $tag);
            });
        })
        ->orderBy('day')->orderBy('period')->get();

        return view('courses.index', ['courses' => $courses, 'q' => $q]);
    }

    public function show(Course $course)
    {
        $course->load(['tags', 'users', 'materials.course.users']);   // 資料ごとの履修確認で、検索が増えないように先に読む
        $reviews = $course->reviews()->with('user')->latest()->get();
        $announcements = $course->announcements()->orderByDesc('published_at')->get();
        return view('courses.show', ['course' => $course, 'reviews' => $reviews, 'announcements' => $announcements]);
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

    public function toggleFavorite(Request $request, Course $course)
    {
        try {
            $request->user()->favoriteCourses()->toggle($course->id);
                } catch (UniqueConstraintViolationException $e) {
                // 他タブで先に追加済み。何もしない
                }
    return back();
    }
}
