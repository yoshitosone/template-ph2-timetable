<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\TimetableEntry;
use App\Models\Course;
use App\Models\User;

class EnrollmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ログインしていないと申請できない(): void
    {
        $before = TimetableEntry::count();

        $response = $this->post('/entries', ['course_id' => 1]);

        $response->assertRedirect('/login');
        $this->assertSame($before, TimetableEntry::count());
    }

        public function test_申請すると申請中の行が1件入る(): void
    {
        $user = User::factory()->create();

        $course = $this->makeCourse();

        $this->actingAs($user)->post('/entries', ['course_id' => $course->id]);

        $this->assertDatabaseHas('timetable_entries', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => TimetableEntry::PENDING,
        ]);
        $this->assertSame(1, TimetableEntry::where('user_id', $user->id)->count());
    }

        private function makeCourse(int $capacity = 2): Course
    {
        $course = new Course();
        $course->forceFill([
            'code' => 'T' . uniqid(), 'name' => 'テスト授業', 'teacher' => '先生',
            'day' => 5, 'period' => 6, 'room' => 'T1', 'credits' => 1,
            'required' => false, 'capacity' => $capacity,
            'semester' => Course::AUTUMN,
        ])->save();
        return $course;
    }

    private function makeEntry(User $user, Course $course, string $status): TimetableEntry
    {
        $entry = new TimetableEntry();
        $entry->user_id = $user->id;
        $entry->course_id = $course->id;
        $entry->status = $status;
        $entry->save();
        return $entry;
    }

        public function test_却下された授業には申請し直せない(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($user, $course, TimetableEntry::REJECTED);

        $response = $this->actingAs($user)->post('/entries', ['course_id' => $course->id]);
        $response->assertSessionHasErrors(['course_id' => '却下された授業には申請し直せません']);

        $this->assertSame(1, TimetableEntry::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('timetable_entries', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => TimetableEntry::REJECTED,
        ]);
    }

        public function test_他の学生の申請は取り消せない(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($owner, $course, TimetableEntry::PENDING);

        $response = $this->actingAs($other)->delete('/entries/' . $entry->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id]);
    }

        public function test_自分の申請中は、取り消せる(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($owner, $course, TimetableEntry::PENDING);

        $response = $this->actingAs($owner)->delete('/entries/' . $entry->id);

        $response->assertRedirect();
        $this->assertDatabaseMissing('timetable_entries', ['id' => $entry->id]);
    }

        public function test_承認された申請は、取り消せない(): void
    {
        $owner = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($owner, $course, TimetableEntry::APPROVED);

        $response = $this->actingAs($owner)->delete('/entries/' . $entry->id);

        $response->assertSessionHasErrors(['entry' => '取り消せるのは申請中の授業だけです']);
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id]);
    }
}
