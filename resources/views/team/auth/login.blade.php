<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digitizing Zone Team Portal</title>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255,255,255,0.88);
            --ink: #15212c;
            --muted: #66717d;
            --line: rgba(21, 33, 44, 0.2);
            --accent: #1d6d5f;
            --accent-dark: #11433a;
            --shadow: 0 24px 60px rgba(21, 33, 44, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            overflow-x: hidden;
            background:
                radial-gradient(circle at top left, rgba(29,109,95,0.12), transparent 32%),
                radial-gradient(circle at bottom right, rgba(181,112,57,0.1), transparent 26%),
                linear-gradient(180deg, #fbf8f2 0%, #eee4d6 100%);
            color: var(--ink);
        }
        .panel {
            width: min(460px, 100%);
            padding: clamp(22px, 3vw, 28px);
            border-radius: 28px;
            background: var(--panel);
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: var(--shadow);
        }
        h1 { margin: 0; font-size: 2rem; line-height: 0.96; letter-spacing: -0.05em; }
        p { color: var(--muted); line-height: 1.6; }
        .field { display: grid; gap: 8px; margin-top: 14px; }
        label { font-size: 0.84rem; font-weight: 700; color: var(--muted); }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 13px 14px;
            background: rgba(255,255,255,0.96);
            color: var(--ink);
            font: inherit;
        }
        input:focus {
            outline: none;
            border-color: rgba(29,109,95,0.58);
            box-shadow: 0 0 0 4px rgba(29,109,95,0.12);
        }
        button {
            width: 100%;
            margin-top: 18px;
            border: 0;
            border-radius: 14px;
            padding: 13px 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
            font-weight: 800;
            font: inherit;
            cursor: pointer;
        }
        .alert {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(181,112,57,0.12);
            border: 1px solid rgba(181,112,57,0.2);
            color: #8d5625;
        }
        @media (max-width: 640px) {
            body { padding: 16px; }
            .panel { border-radius: 22px; padding: 20px; }
            h1 { font-size: 1.75rem; }
        }
    </style>
</head>
<body>
    <form class="panel" method="post" action="{{ url('/team/login') }}">
        @csrf
        <h1>Digitizing Zone<br>Team Portal</h1>
        <p>Authorized users only.</p>

        @if (session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
            <label for="txtLogin">User Name</label>
            <input id="txtLogin" name="txtLogin" type="text" value="{{ old('txtLogin') }}" autofocus>
        </div>

        <div class="field">
            <label for="txtPassword">Password</label>
            <input id="txtPassword" name="txtPassword" type="password">
        </div>

        @include('shared.turnstile')
        <button type="submit">Sign In</button>
    </form>
</body>
</html>
