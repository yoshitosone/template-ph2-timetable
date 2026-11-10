<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>授業一覧</title>
</head>
<body>
    <h1>授業一覧</h1>
    @error('course_id')
        <p>{{ $message }}</p>
    @enderror

    <form method="GET" action="/courses">
        <input type="text" name="q" value="{{ $q }}">
        <button type="submit">検索</button>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>曜日</th>
                <th>時限</th>
                <th>授業名</th>
                <th>担当教員</th>
                <th>教室</th>
                <th>状態</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $course)
                <tr>
                    <td>{{ ['', '月', '火', '水', '木', '金'][$course->day] }}</td>
                    <td>{{ $course->period }}限</td>
                    <td><a href="/courses/{{ $course->id }}">{{ $course->name }}</a></td>
                    <td>{{ $course->teacher }}</td>
                    <td>{{ $course->room }}</td>
                    <td>
                        @if ($course->entries->isNotEmpty())
                            <span>登録済み</span>
                        @else
                            <form method="POST" action="/entries" style="display:inline">
                                @csrf
                                <input type="hidden" name="course_id" value="{{ $course->id }}">
                                <button type="submit">登録</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>