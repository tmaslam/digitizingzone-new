<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digitizing Zone Portal</title>
    <style>
        :root {
            --bg: #f5efe5;
            --panel: rgba(255,255,255,0.86);
            --panel-soft: rgba(255,255,255,0.68);
            --ink: #18232d;
            --muted: #66717d;
            --line: rgba(24, 35, 45, 0.22);
            --line-strong: rgba(24, 35, 45, 0.34);
            --team: #1d6d5f;
            --team-dark: #11433a;
            --admin: #0f5f66;
            --admin-dark: #123c55;
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
                radial-gradient(circle at top left, rgba(29, 109, 95, 0.12), transparent 30%),
                radial-gradient(circle at bottom right, rgba(15, 95, 102, 0.12), transparent 26%),
                linear-gradient(180deg, #fbf7ef 0%, #f1e8da 100%);
        }
        .shell {
            width: min(1080px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }
        .hero {
            padding: clamp(28px, 4vw, 52px);
            background: linear-gradient(160deg, rgba(255,255,255,0.55), rgba(255,255,255,0.16));
        }
        .hero span {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(29, 109, 95, 0.1);
            color: var(--team-dark);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .hero h1 {
            margin: 18px 0 12px;
            font-size: clamp(2.6rem, 5vw, 4.8rem);
            line-height: 0.92;
            letter-spacing: -0.07em;
        }
        .hero p {
            margin: 0;
            max-width: 500px;
            color: var(--muted);
            line-height: 1.75;
        }
        .hero ul {
            margin: 28px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 14px;
        }
        .hero li::before {
            content: "•";
            color: var(--team);
            font-weight: 900;
            margin-right: 10px;
        }
        .panel {
            padding: clamp(22px, 3vw, 32px);
            background: rgba(255,255,255,0.76);
        }
        .card {
            border-radius: 24px;
            padding: 24px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 18px 40px rgba(20, 33, 49, 0.08);
        }
        .card + .card { margin-top: 18px; }
        h2 { margin: 0 0 8px; font-size: 1.25rem; }
        .muted { color: var(--muted); line-height: 1.6; }
        .stack { display: grid; gap: 14px; margin-top: 20px; }
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
        }
        input:focus {
            outline: none;
            border-color: rgba(29, 109, 95, 0.62);
            box-shadow: 0 0 0 4px rgba(29, 109, 95, 0.12);
        }
        .btn {
            border: 0;
            border-radius: 14px;
            padding: 13px 16px;
            min-height: 46px;
            color: white;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1.1;
            text-decoration: none;
        }
        .btn-team { background: linear-gradient(135deg, var(--team), var(--team-dark)); }
        .btn-admin { background: linear-gradient(135deg, var(--admin), var(--admin-dark)); }
        .btn-soft {
            background: var(--panel-soft);
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .alert {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(180, 35, 24, 0.08);
            color: #9d2d17;
            border: 1px solid rgba(180, 35, 24, 0.18);
        }
        @media (max-width: 920px) {
            .shell { grid-template-columns: 1fr; }
            .hero, .panel { padding: 26px; }
        }
        @media (max-width: 640px) {
            body { padding: 16px; }
            .shell { border-radius: 24px; }
            .card { border-radius: 20px; padding: 18px; }
            .hero h1 { font-size: clamp(2.1rem, 14vw, 3rem); }
            .hero ul { gap: 10px; }
            .actions > * { width: 100%; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <span>Portal Access</span>
        <h1>Digitizing Zone</h1>
        <p>Select the correct sign-in area. Team and supervisor access share one portal, and admin access stays separate.</p>
        <ul>
            <li>Team and supervisor work area</li>
            <li>Admin management area</li>
            <li>Authorized users only</li>
        </ul>
    </section>

    <section class="panel">
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        @if (session('success'))
            <div class="alert" style="background: rgba(21, 115, 71, 0.08); color: #1d6f46; border-color: rgba(21, 115, 71, 0.16);">
                {{ session('success') }}
            </div>
        @endif

        <div class="card">
            <h2>Team / Supervisor Sign In</h2>
            <p class="muted">Use your team or supervisor username and password.</p>
            <form method="post" action="{{ url('/team/login') }}" class="stack">
                @csrf
                <div>
                    <label for="txtLogin">Username</label>
                    <input id="txtLogin" type="text" name="txtLogin" value="{{ old('txtLogin') }}" autocomplete="username" required autofocus>
                </div>
                <div>
                    <label for="txtPassword">Password</label>
                    <input id="txtPassword" type="password" name="txtPassword" autocomplete="current-password" required>
                </div>
                <button class="btn btn-team" type="submit">Sign In To Team Portal</button>
            </form>
        </div>

        <div class="card">
            <h2>Admin Access</h2>
            <p class="muted">Open the separate admin sign-in page if you are managing orders, billing, customers, teams, and reports.</p>
            <div class="actions">
                <a class="btn btn-admin" href="{{ url('/v') }}">Open Admin Login</a>
                <a class="btn btn-soft" href="{{ url('/team') }}">Open Team Portal</a>
            </div>
        </div>
    </section>
</div>
</body>
</html>
