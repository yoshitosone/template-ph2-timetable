<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>時間割一覧</title>
</head>
<body>
    <div>
        <span>{{ Auth::user()->name }}さんの時間割</span>
        <form method="POST" action="/logout" style="display:inline">
            @csrf
            <button type="submit">ログアウト</button>
        </form>
    </div>
    <h1>時間割一覧</h1>

    <h2>今学期の単位（{{ $semester === \App\Models\Course::SPRING ? '春' : '秋' }}学期）</h2>
    <p>承認済み {{ $approvedCredits }} / {{ $maxCredits }} 単位</p>
    @if ($isOver)
        <p style="color: red;"><strong>上限（{{ $maxCredits }}単位）を超えています</strong></p>
    @endif

    <h3>未履修の必修授業</h3>
    @forelse ($missingRequired as $course)
        <p>{{ $course->code }} {{ $course->name }}（{{ $course->credits }}単位／{{ $course->semester === \App\Models\Course::SPRING ? '春' : '秋' }}学期）</p>
    @empty
        <p>未履修の必修授業はありません</p>
    @endforelse

    <h2>最近のお知らせ</h2>
    @forelse ($announcements as $announcement)
        <p>{{ $announcement->published_at->format('Y/m/d H:i') }}　【{{ $announcement->course->name }}】{{ $announcement->title }}</p>
    @empty
        <p>お知らせはありません</p>
    @endforelse

    @error('entry')
        <p>{{ $message }}</p>
    @enderror

    @if ($entries->isEmpty())
        <p>まだ授業が登録されていません</p>
    @else
        <table border="1">
            <thead>
                <tr>
                    <th>曜日</th>
                    <th>時限</th>
                    <th>授業名</th>
                    <th>履修者数</th>
                    <th>担当教員</th>
                    <th>教室</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ ['', '月', '火', '水', '木', '金'][$entry->course->day] }}</td>
                        <td>{{ $entry->course->period }}限</td>
                        <td>{{ $entry->course->name }}</td>
                        <td>{{ $entry->course->users_count }}人 / 定員：{{ $entry->course->capacity }}人</td>
                        <td>{{ $entry->course->teacher }}</td>
                        <td>{{ $entry->course->room }}</td>
                        <td>{{ [\App\Models\TimetableEntry::PENDING => '申請中', \App\Models\TimetableEntry::APPROVED => '承認', \App\Models\TimetableEntry::REJECTED => '却下'][$entry->status] }}</td>
                        <td>
                            @if ($entry->status === \App\Models\TimetableEntry::PENDING)
                                <form method="POST" action="/entries/{{ $entry->id }}" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">登録取消</button>
                                </form>
                            @elseif ($entry->status === \App\Models\TimetableEntry::APPROVED)
                                <span>登録済み</span>
                            @elseif ($entry->status === \App\Models\TimetableEntry::REJECTED)
                                <span>登録不可</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>