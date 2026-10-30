<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>授業登録</title>
</head>
<body>
    <h1>授業登録</h1>

    <form method="POST" action="/entries">
        @csrf

        <div>
            <label for="day">曜日</label>
            <select id="day" name="day" required>
                <option value="">選択してください</option>
                <option value="1" @selected(old('day') == 1)>月</option>
                <option value="2" @selected(old('day') == 2)>火</option>
                <option value="3" @selected(old('day') == 3)>水</option>
                <option value="4" @selected(old('day') == 4)>木</option>
                <option value="5" @selected(old('day') == 5)>金</option>
            </select>
            @error('day')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="period">時限</label>
            <select id="period" name="period" required>
                <option value="">選択してください</option>
                @for ($period = 1; $period <= 6; $period++)
                    <option value="{{ $period }}" @selected(old('period') == $period)>{{ $period }}限</option>
                @endfor
            </select>
            @error('period')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="title">授業名</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required>
            @error('title')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="room">教室</label>
            <input id="room" type="text" name="room" value="{{ old('room') }}">
            @error('room')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">登録する</button>
    </form>
</body>
</html>
