<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <!-- 必要に応じてCSSデザインを追加してください -->
</head>
<body>
    <!-- 区間1：月曜日の授業 -->
    <div>
        <h2>区間1:月曜日の授業</h2>
        <p>時限の早い順に並んでいます。</p>
        <table border="1">
            <thead>
                <tr>
                    <th>時限</th>
                    <th>授業名</th>
                    <th>担当教員</th>
                    <th>教室</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($mondayCourses as $course)
                    <tr>
                        <td>{{ $course->period }}</td>
                        <td>{{ $course->name }}</td>
                        <td>{{ $course->teacher }}</td>
                        <td>{{ $course->room }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- 区間2：必修科目 -->
    <div>
        <h2>区間2:必修科目</h2>
        <p>科目コードの順に並んでいます。</p>
        <table border="1">
            <thead>
                <tr>
                    <th>授業名</th>
                    <th>単位数</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requiredCourses as $course)
                    <tr>
                        <td>{{ $course->name }}</td>
                        <td>{{ $course->credits }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>