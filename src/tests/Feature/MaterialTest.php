<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaterialTest extends TestCase
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

    // 資料を1件作り、保存先にも、ファイルを置く
    private function makeMaterial(Course $course, bool $retired = false): CourseMaterial
    {
        $path = 'materials/' . uniqid() . '.pdf';
        Storage::disk('local')->put($path, 'dummy pdf');

        $material = new CourseMaterial();
        $material->forceFill([
            'course_id' => $course->id, 'path' => $path, 'original_name' => '資料.pdf',
            'retired_at' => $retired ? now() : null,
        ])->save();
        return $material;
    }

    public function test_教務課はPDFをアップロードできる(): void
    {
        Storage::fake('local');   // 本物の保存先を汚さない
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $file = UploadedFile::fake()->create('資料.pdf', 100, 'application/pdf');   // 100KB

        $response = $this->actingAs($admin)->post('/admin/courses/' . $course->id . '/materials', ['file' => $file]);

        $response->assertRedirect('/courses/' . $course->id);
        $this->assertDatabaseHas('course_materials', [
            'course_id' => $course->id,
            'original_name' => '資料.pdf',     // 画面に出す名前は、アップロードしたファイル名
        ]);
        // 保存先に、実際にファイルが置かれていること
        Storage::disk('local')->assertExists(CourseMaterial::first()->path);
    }

    public function test_学生はアップロードできない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $file = UploadedFile::fake()->create('資料.pdf', 100, 'application/pdf');

        $response = $this->actingAs($student)->post('/admin/courses/' . $course->id . '/materials', ['file' => $file]);

        $response->assertStatus(403);   // 権限がないときの番号
        $this->assertDatabaseCount('course_materials', 0);   // 1件も増えていないこと
    }

    public function test_PDF以外はアップロードできない(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $file = UploadedFile::fake()->create('画像.png', 100, 'image/png');   // PDF ではない種類（image/png）

        $response = $this->actingAs($admin)->post('/admin/courses/' . $course->id . '/materials', ['file' => $file]);

        $response->assertSessionHasErrors('file');   // エラーが付く入力名
        $this->assertDatabaseCount('course_materials', 0);
    }

    public function test_10MBを超えるファイルはアップロードできない(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        // 10MB をちょうど1KB 超える大きさ（単位は KB）
        $file = UploadedFile::fake()->create('大きい.pdf', 10241, 'application/pdf');

        $response = $this->actingAs($admin)->post('/admin/courses/' . $course->id . '/materials', ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('course_materials', 0);
    }

    public function test_承認済みの学生はダウンロードできる(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $material = $this->makeMaterial($course);

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/download');

        $response->assertOk();
    }

    public function test_申請中の学生はダウンロードできない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::PENDING);
        $material = $this->makeMaterial($course);

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/download');

        $response->assertStatus(403);
    }

    public function test_履修していない学生はダウンロードできない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();    // この授業に、申請していない
        $material = $this->makeMaterial($this->makeCourse());

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/download');

        $response->assertStatus(403);
    }

    public function test_未ログインはダウンロードできない(): void
    {
        Storage::fake('local');
        $material = $this->makeMaterial($this->makeCourse());

        $response = $this->get('/materials/' . $material->id . '/download');

        $response->assertRedirect('/login');
    }

    public function test_承認済みの学生はプレビューできる(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $material = $this->makeMaterial($course);

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/preview');

        $response->assertOk();
    }

    public function test_履修していない学生はプレビューできない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $material = $this->makeMaterial($this->makeCourse());

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/preview');

        $response->assertStatus(403);
    }

    public function test_教務課は差し替えられる(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $course = $this->makeCourse();
        $old = $this->makeMaterial($course);
        $file = UploadedFile::fake()->create('新しい.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post('/admin/materials/' . $old->id . '/replace', ['file' => $file]);

        $response->assertRedirect('/courses/' . $course->id);
        // 新しい資料が、配布中（retired_at が NULL）で増えている
        $this->assertDatabaseHas('course_materials', ['course_id' => $course->id, 'original_name' => '新しい.pdf', 'retired_at' => null]);
        // 古い資料は、配布をやめた状態（retired_at が入った）になっているが、行は残っている
        $this->assertNotNull($old->fresh()->retired_at);
    }

    public function test_差し替え後は古い資料を学生がダウンロードできない(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $old = $this->makeMaterial($course);
        $file = UploadedFile::fake()->create('新しい.pdf', 100, 'application/pdf');
        $this->actingAs($admin)->post('/admin/materials/' . $old->id . '/replace', ['file' => $file]);

        $response = $this->actingAs($student)->get('/materials/' . $old->id . '/download');

        $response->assertStatus(403);
    }

    public function test_学生は差し替えられない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $old = $this->makeMaterial($course);
        $file = UploadedFile::fake()->create('新しい.pdf', 100, 'application/pdf');

        $response = $this->actingAs($student)->post('/admin/materials/' . $old->id . '/replace', ['file' => $file]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('course_materials', 1);   // 増えていない
        $this->assertNull($old->fresh()->retired_at);         // 古い資料も、配布中のまま
    }

    public function test_教務課は資料を削除できる(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $material = $this->makeMaterial($this->makeCourse());

        $response = $this->actingAs($admin)->delete('/admin/materials/' . $material->id);

        $response->assertRedirect();
        $this->assertNotNull($material->fresh()->retired_at);            // 配布をやめた
        Storage::disk('local')->assertExists($material->path);            // ファイルは残っている
    }

    public function test_削除した資料は学生がダウンロードできない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $material = $this->makeMaterial($course, true);   // 配布をやめた資料

        $response = $this->actingAs($student)->get('/materials/' . $material->id . '/download');

        $response->assertStatus(403);
    }

    public function test_学生は削除できない(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $material = $this->makeMaterial($course);

        $response = $this->actingAs($student)->delete('/admin/materials/' . $material->id);

        $response->assertStatus(403);
        $this->assertNull($material->fresh()->retired_at);    // 配布中のまま
    }

    public function test_授業の詳細画面が開ける(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $course = $this->makeCourse();
        $this->makeEntry($student, $course, TimetableEntry::APPROVED);
        $this->makeMaterial($course);

        $response = $this->actingAs($student)->get('/courses/' . $course->id);

        $response->assertOk();
        $response->assertSee('資料.pdf');   // 履修している学生には、資料の名前が見える
    }

    public function test_配布停止済みの資料を削除しても日時は変わらない(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $material = $this->makeMaterial($this->makeCourse(), true);
        // 配布をやめた日時を、昔の日付にしておく
        $material->forceFill(['retired_at' => '2026-01-01 00:00:00'])->save();

        $this->actingAs($admin)->delete('/admin/materials/' . $material->id);

        // もう一度削除しても、日付は昔のまま（今日の日付に上書きされない）
        $this->assertSame('2026-01-01', $material->fresh()->retired_at->format('Y-m-d'));   // 2026-01-01 の形
    }
}
