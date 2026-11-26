<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Grade;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCourse(): Course
    {
        $course = new Course();
        $course->forceFill([
            'code' => 'T' . uniqid(), 'name' => 'テスト授業', 'teacher' => '先生',
            'day' => 5, 'period' => 6, 'room' => 'T1', 'credits' => 1,
            'required' => false, 'capacity' => 40, 'semester' => Course::AUTUMN,
        ])->save();
        return $course;
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeEntry(User $user, Course $course, string $status): void
    {
        $entry = new TimetableEntry();
        $entry->user_id = $user->id;
        $entry->course_id = $course->id;
        $entry->status = $status;
        $entry->save();
    }


    private const HEADER = "氏名,メールアドレス,成績\n";

    // この授業を承認済みで履修している学生を作る
    private function makeStudent(Course $course, string $email): User
    {
        $student = User::factory()->create(['email' => $email]);
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        return $student;
    }

    // CSV の中身を、教務課として、アップロードする
    private function importAs(User $user, Course $course, string $body)
    {
        $file = UploadedFile::fake()->createWithContent('grades.csv', $body);

        return $this->actingAs($user)->post('/admin/courses/' . $course->id . '/grades', ['csv_file' => $file]);
    }

    public function test_教務課は名簿をダウンロードできる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $student = User::factory()->create();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);

        $response = $this->actingAs($admin)->get('/admin/courses/' . $course->id . '/roster');

        $response->assertOk();
        // CSV の中身に、学生のメールアドレスが入っていること
        $this->assertStringContainsString($student->email, $response->streamedContent());
    }

    public function test_名簿には承認済みの学生だけが入る(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $approved = User::factory()->create();
        $pending = User::factory()->create();
        $this->makeEntry($approved, $course, TimetableEntry::APPROVED);
        $this->makeEntry($pending, $course, TimetableEntry::PENDING);   // 申請中

        $content = $this->actingAs($admin)->get('/admin/courses/' . $course->id . '/roster')->streamedContent();

        $this->assertStringContainsString($approved->email, $content);
        $this->assertStringNotContainsString($pending->email, $content);
    }

    public function test_名簿はExcelで開けるようBOMが付く(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $content = $this->actingAs($admin)->get('/admin/courses/' . $course->id . '/roster')->streamedContent();

        // UTF-8 の BOM は、先頭の3バイト（EF BB BF）
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
    }

    public function test_数式として働く文字で始まる名前は無効化される(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $student = User::factory()->create(['name' => '=SUM(1+1)']);
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);

        $content = $this->actingAs($admin)->get('/admin/courses/' . $course->id . '/roster')->streamedContent();

        // 先頭に ' が付いて、数式として動かない形で出る
        $this->assertStringContainsString("'=SUM(1+1)", $content);
        $this->assertStringNotContainsString(',=SUM(1+1)', $content);   // 先頭が = のままのセルが、残っていない
    }

    public function test_学生は名簿をダウンロードできない(): void
    {
        $student = User::factory()->create();
        $course = $this->makeCourse();

        $response = $this->actingAs($student)->get('/admin/courses/' . $course->id . '/roster');

        $response->assertStatus(403);
    }

    public function test_未ログインは名簿をダウンロードできない(): void
    {
        $course = $this->makeCourse();

        $response = $this->get('/admin/courses/' . $course->id . '/roster');

        $response->assertRedirect('/login');
    }

    public function test_教務課は成績を一括登録できる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $a = $this->makeStudent($course, 'a@example.com');
        $b = $this->makeStudent($course, 'b@example.com');

        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,A\nB子,b@example.com,C\n");

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grades', ['user_id' => $a->id, 'course_id' => $course->id, 'grade' => 'A']);
        $this->assertDatabaseHas('grades', ['user_id' => $b->id, 'course_id' => $course->id, 'grade' => 'C']);   // B子の成績
    }

    public function test_学生は成績を取り込めない(): void
    {
        $student = $this->makeStudent($course = $this->makeCourse(), 'a@example.com');

        $response = $this->importAs($student, $course, self::HEADER . "A太郎,a@example.com,A\n");

        $response->assertStatus(403);
        $this->assertDatabaseCount('grades', 0);   // 1件も増えていないこと
    }

    public function test_成績がすでにある学生は上書きされる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $a = $this->makeStudent($course, 'a@example.com');
        Grade::create(['user_id' => $a->id, 'course_id' => $course->id, 'grade' => 'C']);

        $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,A\n");

        $this->assertDatabaseHas('grades', ['user_id' => $a->id, 'course_id' => $course->id, 'grade' => 'A']);
        $this->assertDatabaseCount('grades', 1);   // 増えずに、1件のまま
    }

    public function test_不正な行が1行でもあれば1行も登録しない(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');
        $this->makeStudent($course, 'b@example.com');

        // 1行目のデータは正しいが、2行目のデータの成績が X で不正
        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,A\nB子,b@example.com,X\n");

        $response->assertSessionHasErrors('csv');
        $this->assertDatabaseCount('grades', 0);   // 正しい行も、登録されていない
    }

    public function test_成績がA〜F以外の行はエラーになる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');

        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,E\n");

        // 見出しが1行目なので、このデータは何行目？
        $response->assertSessionHasErrors(['csv' => '2行目：成績は A〜F のいずれかにしてください']);
    }

    public function test_履修していないメールの行はエラーになる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        User::factory()->create(['email' => 'other@example.com']);   // 登録はあるが、この授業は履修していない

        $response = $this->importAs($admin, $course, self::HEADER . "他人,other@example.com,A\n");

        $response->assertSessionHasErrors(['csv' => '2行目：履修していない学生です（other@example.com）']);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_成績が空欄の行はエラーになる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');

        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,\n");

        $response->assertSessionHasErrors(['csv' => '2行目：成績が空欄です']);
    }

    public function test_同じメールが2行あると重複としてエラーになる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');

        // 3行目に、2行目と同じメールを書く
        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,A\nA太郎,a@example.com,B\n");

        $response->assertSessionHasErrors(['csv' => '3行目：メールアドレスが2行目と重複しています']);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_見出しが違うファイルは全体がエラーになる(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');

        $response = $this->importAs($admin, $course, "名前,メール,成績\nA太郎,a@example.com,A\n");

        $response->assertSessionHasErrors(['csv' => '1行目：見出しは「氏名,メールアドレス,成績」にしてください']);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_BOM付きのファイルも取り込める(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $a = $this->makeStudent($course, 'a@example.com');

        // 名簿をダウンロードして、そのまま返してもらった形（先頭に BOM が付いている）
        $response = $this->importAs($admin, $course, "\xEF\xBB\xBF" . self::HEADER . "A太郎,a@example.com,A\n");

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grades', ['user_id' => $a->id, 'grade' => 'A']);
    }

    public function test_メールの前後の空白と大文字小文字は無視される(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $a = $this->makeStudent($course, 'a@example.com');

        $response = $this->importAs($admin, $course, self::HEADER . "A太郎, A@Example.COM ,A\n");

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('grades', ['user_id' => $a->id, 'grade' => 'A']);
    }

    public function test_不正な行はすべてまとめて表示される(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');
        $this->makeStudent($course, 'b@example.com');

        // 2行目と3行目が、どちらも不正（成績が X と Z）
        $response = $this->importAs($admin, $course, self::HEADER . "A太郎,a@example.com,X\nB子,b@example.com,Z\n");

        $response->assertSessionHasErrors(['csv' => '2行目：成績は A〜F のいずれかにしてください']);
        $response->assertSessionHasErrors(['csv' => '3行目：成績は A〜F のいずれかにしてください']);
    }

    public function test_授業の詳細画面が開ける(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();

        $response = $this->actingAs($admin)->get('/courses/' . $course->id);

        $response->assertOk();
        $response->assertSee('成績を取り込む');
    }

    public function test_学生は自分の成績を見られる(): void
    {
        $course = $this->makeCourse();
        $me = $this->makeStudent($course, 'me@example.com');
        $other = $this->makeStudent($course, 'other@example.com');
        Grade::create(['user_id' => $me->id, 'course_id' => $course->id, 'grade' => 'F']);
        Grade::create(['user_id' => $other->id, 'course_id' => $course->id, 'grade' => 'B']);

        $response = $this->actingAs($me)->get('/entries');

        $response->assertOk();
        $response->assertSee('成績：F');        // 自分の成績（F）
        $response->assertDontSee('成績：B');    // 他の学生の成績（B）は見えない
    }

    public function test_成績がまだない授業はハイフンで表示される(): void
    {
        $course = $this->makeCourse();
        $me = $this->makeStudent($course, 'me@example.com');

        $response = $this->actingAs($me)->get('/entries');

        $response->assertSee('成績：-');   // まだ成績がないときの表示
    }

    public function test_UTF8でないファイルは文字コードの案内が出る(): void
    {
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $this->makeStudent($course, 'a@example.com');
        // Excel の普通の「CSV」と同じ、Shift_JIS の文字コードにして渡す
        $body = mb_convert_encoding(self::HEADER . "A太郎,a@example.com,A\n", 'Shift_JIS', 'UTF-8');   // Shift_JIS の拡張版の名前

        $response = $this->importAs($admin, $course, $body);

        $response->assertSessionHasErrors(['csv' => 'ファイルの文字コードが UTF-8 ではありません。Excel で「CSV UTF-8（コンマ区切り）」を選んで保存し直してください']);
        $this->assertDatabaseCount('grades', 0);   // 登録されていない
    }
}
