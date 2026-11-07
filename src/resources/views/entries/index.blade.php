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

    @if ($entries->isEmpty())
        <p>まだ授業が登録されていません</p>
    @else
        <table border="1">
            <thead>
                <tr>
                    <th>曜日</th>
                    <th>時限</th>
                    <th>授業名</th>
                    <th>担当教員</th>
                    <th>教室</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ ['', '月', '火', '水', '木', '金'][$entry->course->day] }}</td>
                        <td>{{ $entry->course->period }}限</td>
                        <td>{{ $entry->course->name }}</td>
                        <td>{{ $entry->course->teacher }}</td>
                        <td>{{ $entry->course->room }}</td>
                        <td>
                            <form method="POST" action="/entries/{{ $entry->id }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit">削除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>