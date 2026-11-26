<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TimetableEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use DatabaseTransactions;

    // 日付を、秋学期の月（11月）に固定する。実行する日で、結果が変わらないようにするため
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();   // 固定を解く
        parent::tearDown();
    }

    private function makeCourse(int $credits, string $semester = Course::AUTUMN, bool $required = false): Course
    {
        $course = new Course();
        $course->forceFill([
            'code' => 'T' . uniqid(), 'name' => 'テスト授業' . uniqid(), 'teacher' => '先生',
            'day' => 5, 'period' => 6, 'room' => 'T1', 'credits' => $credits,
            'required' => $required, 'capacity' => 40, 'semester' => $semester,
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

    public function test_未ログインでは単位を見られない(): void
    {
        $response = $this->get('/entries');

        $response->assertRedirect('/login');
    }

    public function test_承認済みの授業の単位が合計に入る(): void
    {
        $user = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertOk();   // 画面が、開けること
        $response->assertSee('承認済み 2 / 24 単位');
    }

    public function test_申請中の授業は合計に入らない(): void
    {
        $user = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::APPROVED);
        // 申請中の授業（2単位）も入れる。合計には入らないはず
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::PENDING);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('承認済み 2 / 24 単位');
    }

    public function test_却下の授業は合計に入らない(): void
    {
        $user = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::APPROVED);
        // 却下の授業（2単位）も入れる。合計には入らないはず
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::REJECTED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('承認済み 2 / 24 単位');
    }

    public function test_今学期以外の授業は合計に入らない(): void
    {
        $user = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2, Course::AUTUMN), TimetableEntry::APPROVED);
        // 今学期（秋）ではない学期の授業を、承認済みで入れる
        $this->makeEntry($user, $this->makeCourse(2, Course::SPRING), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('承認済み 2 / 24 単位');
    }

    public function test_自分の単位だけが表示される(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2), TimetableEntry::APPROVED);
        $this->makeEntry($other, $this->makeCourse(3), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('承認済み 2 / 24 単位');
        // 他人の分が混ざった合計（2 + 3）になっていないこと
        $response->assertDontSee('承認済み 5 / 24 単位');
    }

    public function test_24単位ちょうどは超過にならない(): void
    {
        $user = User::factory()->create();
        // 1つの授業で、ちょうど上限の単位にする
        $this->makeEntry($user, $this->makeCourse(24), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('承認済み 24 / 24 単位');
        $response->assertDontSee('を超えています');   // 超過のときだけ出る文言（画面の赤字）
    }

    public function test_25単位以上は超過と表示される(): void
    {
        $user = User::factory()->create();
        // 上限を、1つだけ超える単位にする
        $this->makeEntry($user, $this->makeCourse(25), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('を超えています');       // 超過のときだけ出る文言（画面の赤字）
    }

    public function test_未承認の必修授業が未履修として表示される(): void
    {
        $user = User::factory()->create();
        // 必修の授業を作る（makeCourse の3つ目の引数が、必修かどうか）
        $course = $this->makeCourse(2, Course::AUTUMN, true);
        $this->makeEntry($user, $course, TimetableEntry::PENDING);   // 承認されていない状態

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee($course->code);   // 授業のコードの列
    }

    public function test_承認済みの必修授業は未履修に出ない(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse(2, Course::AUTUMN, true);
        $this->makeEntry($user, $course, TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertDontSee($course->code);
    }

    public function test_上限値は学生がリクエストで変えられない(): void
    {
        $user = User::factory()->create();

        // URL に、上限値を変えようとする値（max=100）を付けて開く
        $response = $this->actingAs($user)->get('/entries?max=100');

        $response->assertSee('/ 24 単位');
        $response->assertDontSee('/ 100 単位');
    }

    public function test_春の月は春学期の授業だけが合計に入る(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');   // 5月は、春学期の月
        $user = User::factory()->create();
        $this->makeEntry($user, $this->makeCourse(2, Course::AUTUMN), TimetableEntry::APPROVED);
        $this->makeEntry($user, $this->makeCourse(3, Course::SPRING), TimetableEntry::APPROVED);

        $response = $this->actingAs($user)->get('/entries');

        $response->assertSee('春学期');
        $response->assertSee('承認済み 3 / 24 単位');
    }
}
