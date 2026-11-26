<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\Course;
use App\Models\TimetableEntry;
use App\Models\User;

class ApprovalTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic feature test example.
     */

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

        public function test_学生は承認の画面を開けない(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/admin/entries');

        $response->assertStatus(403);
    }

    public function test_学生は承認できない(): void
    {
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($student, $course, TimetableEntry::PENDING);

        $response = $this->actingAs($student)->post('/admin/entries/' . $entry->id . '/approve');

        $response->assertStatus(403);
        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id, 'status' => TimetableEntry::PENDING]);
    }

    public function test_管理者が承認すると承認済みになる(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($student, $course, TimetableEntry::PENDING);

        $this->actingAs($admin)->post('/admin/entries/' . $entry->id . '/approve');

        $this->assertDatabaseHas('timetable_entries', ['id' => $entry->id, 'status' => TimetableEntry::APPROVED]);
    }

        public function test_定員まであと1人なら承認できる(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $course = $this->makeCourse(2);

        // 承認済みを1人入れる（定員2のうち、1人うまっている）
        $this->makeEntry(User::factory()->create(), $course, TimetableEntry::APPROVED);
        // 承認する対象（申請中）
        $target = $this->makeEntry(User::factory()->create(), $course, TimetableEntry::PENDING);

        $this->actingAs($admin)->post('/admin/entries/' . $target->id . '/approve');

        $this->assertDatabaseHas('timetable_entries', [
            'id' => $target->id,
            'status' => TimetableEntry::APPROVED,
        ]);
    }

    public function test_定員に達した授業は承認できない(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $course = $this->makeCourse(2);

        // 承認済みを2人入れる（定員2が、うまっている）
        $this->makeEntry(User::factory()->create(), $course, TimetableEntry::APPROVED);
        $this->makeEntry(User::factory()->create(), $course, TimetableEntry::APPROVED);
        // 承認しようとする対象（申請中）
        $target = $this->makeEntry(User::factory()->create(), $course, TimetableEntry::PENDING);

        $this->actingAs($admin)->post('/admin/entries/' . $target->id . '/approve');

        $this->assertDatabaseHas('timetable_entries', [
            'id' => $target->id,
            'status' => TimetableEntry::PENDING,
        ]);
    }

        public function test_却下した申請はあとから承認できない(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $entry = $this->makeEntry($student, $course, TimetableEntry::REJECTED);

        $this->actingAs($admin)->post('/admin/entries/' . $entry->id . '/approve');

        $this->assertDatabaseHas('timetable_entries', [
            'id' => $entry->id,
            'status' => TimetableEntry::REJECTED,
        ]);
    }

}
