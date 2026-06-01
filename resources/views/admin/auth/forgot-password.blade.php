<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Digitizing Zone Admin</title>
    <link rel="icon" type="image/png" href="{{ url('images/logo.png') }}">
    <style>
        :root {
            --bg: #f4ede2;
            --ink: #19232e;
            --muted: #6b7280;
            --accent: #0f5f66;
            --accent-dark: #123c55;
            --line: rgba(25, 35, 46, 0.26);
            --line-strong: rgba(25, 35, 46, 0.38);
            --shadow: 0 28px 70px rgba(20, 33, 49, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            color: var(--ink);
            overflow-x: hidden;
            background:
                radial-gradient(circle at top left, rgba(15, 95, 102, 0.14), transparent 30%),
                radial-gradient(circle at bottom right, rgba(197, 107, 34, 0.12), transparent 24%),
                linear-gradient(180deg, #fbf7ef 0%, #f3eadb 100%);
        }
        .shell {
            width: min(560px, 100%);
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            padding: clamp(28px, 5vw, 48px);
        }
        h1 { margin: 0 0 8px; font-size: 1.6rem; letter-spacing: -0.03em; }
        .muted { color: var(--muted); line-height: 1.6; margin: 0 0 24px; }
        .stack { display: grid; gap: 14px; }
        label { font-size: 0.86rem; font-weight: 700; color: var(--muted); }
        input {
            width: 100%;
            margin-top: 8px;
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            padding: 13px 14px;
            background: #fffefc;
            color: var(--ink);
            min-height: 48px;
            line-height: 1.35;
            box-shadow: inset 0 1px 2px rgba(25, 35, 46, 0.05);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 1rem;
        }
        input:focus {
            outline: none;
            border-color: rgba(15, 95, 102, 0.62);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(15, 95, 102, 0.12);
        }
        button {
            border: 0;
            border-radius: 14px;
            padding: 13px 16px;
            min-height: 46px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .alert {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(180, 35, 24, 0.08);
            color: #9d2d17;
            border: 1px solid rgba(180, 35, 24, 0.18);
        }
        .success-note {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(21, 115, 71, 0.08);
            color: #1d6f46;
            border: 1px solid rgba(21, 115, 71, 0.16);
        }
        .back-link {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.88rem;
            text-decoration: none;
        }
        .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="shell">
    <h1>Forgot Password</h1>
    <p class="muted">Enter your admin username or email address and we will send a reset link if the account exists.</p>

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    @if (session('success'))
        <div class="success-note">{{ session('success') }}</div>
    @endif

    <form method="post" action="{{ url('/v/forgot-password') }}" class="stack">
        @csrf
        <div>
            <label for="identity">Username or Email</label>
            <input id="identity" type="text" name="identity" value="{{ old('identity') }}" autocomplete="username" required>
        </div>
        @include('shared.turnstile')
        <button type="submit">Send Reset Link</button>
    </form>

    <a class="back-link" href="{{ url('/v') }}">&larr; Back to Admin Login</a>
</div>
</body>
</html>
