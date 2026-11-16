<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PulseSync — Sign in</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
    <main class="auth-card">
        <a class="brand" href="/">pulse<span>sync</span><i></i></a>
        <p class="eyebrow">YOUR SIGNAL, IN FOCUS</p>
        <h1>Make every visit count.</h1>
        <p class="muted">Connect your WordPress site and see the leads behind the traffic.</p>
        @if ($errors->any())
            <div class="form-error" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="/login" class="auth-form">
            @csrf
            <label>Email<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
            <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
            <button type="submit" class="primary-button">Sign in <span aria-hidden="true">→</span></button>
        </form>
        <details class="register">
            <summary>New to PulseSync? Create an account</summary>
            <form method="post" action="/register" class="auth-form">
                @csrf
                <label>Name<input name="name" autocomplete="name" required></label>
                <label>Email<input name="email" type="email" autocomplete="email" required></label>
                <label>Password<input name="password" type="password" minlength="10" autocomplete="new-password" required></label>
                <label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required></label>
                <button type="submit" class="secondary-button">Create account</button>
            </form>
        </details>
        <p class="auth-foot">A clear view of your growing audience.</p>
    </main>
</body>
</html>
