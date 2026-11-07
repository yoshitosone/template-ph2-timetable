<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ユーザー登録</title>
</head>
<body>
    <h1>ユーザー登録</h1>

    <form method="POST" action="/register">
        @csrf

        <div>
            <label for="name">名前</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}">
            @error('name')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email">メールアドレス</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}">
            @error('email')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password">パスワード</label>
            <input type="password" name="password" id="password">
            @error('password')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation">パスワード（確認用）</label>
            <input type="password" name="password_confirmation" id="password_confirmation">
            @error('password_confirmation')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">登録する</button>
    </form>

    <a href="{{ route('login') }}">ログイン</a>
</body>
</html>
