<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Review;

class ReviewController extends Controller
{
    public function destroy(Review $review)
    {
        Gate::authorize('delete', $review);
        $review->delete();
        return redirect('/courses/' . $review->course_id);
    }
}
