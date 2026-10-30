<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>時間割一覧</title>
</head>
<body>
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
                    <th>教室</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ ['', '月', '火', '水', '木', '金'][$entry->day] }}</td>
                        <td>{{ $entry->period }}限</td>
                        <td>{{ $entry->title }}</td>
                        <td>{{ $entry->room }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>