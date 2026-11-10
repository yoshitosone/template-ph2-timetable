<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimetableEntry;
use App\Models\Course;
use Illuminate\Support\Facades\Gate;

class TimetableEntryController extends Controller
{
    public function index(Request $request)
    {
        $entries = $request->user()->entries()->with('course')
            ->join('courses', 'timetable_entries.course_id', '=', 'courses.id')
            ->select('timetable_entries.*')
            ->orderBy('courses.day')
            ->orderBy('courses.period')
            ->get();
        
        return view('entries.index', ['entries' => $entries]);
    }

    public function create()
    {
        return view('entries.create', ['courses' => Course::orderBy('day')->orderBy('period')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ],[
            'course_id.required' => '授業を一覧から選んでください',
            'course_id.exists' => '授業を一覧から選んでください',
        ]);
        
        $course = Course::find($validated['course_id']);
        $exists = $request->user()->entries()->whereHas('course', function ($query) use ($course) {
            $query->where('day', $course->day)
                ->where('period', $course->period);
        })->exists();

        if ($exists) {
            return back()->withErrors(['course_id' => 'その曜日・時限にはすでに授業が登録されています'])
                ->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        TimetableEntry::create($validated);

        return redirect('/entries');
    }

    public function destroy($id)
    {
        $entry = TimetableEntry::findOrFail($id);
        Gate::authorize('delete', $entry);
        $entry->delete();

        return redirect('/entries');
    }
}