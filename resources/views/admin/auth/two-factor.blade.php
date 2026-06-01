<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digitizing Zone Admin — Verification</title>
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
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }
        .hero { padding: clamp(28px, 4vw, 54px); background: linear-gradient(160deg, rgba(255,255,255,0.55), rgba(255,255,255,0.14)); }
        .hero span { display: inline-block; padding: 8px 12px; border-radius: 999px; background: rgba(15, 95, 102, 0.1); color: var(--accent-dark); font-size: 0.76rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
        .hero h1 { margin: 18px 0 12px; font-size: clamp(2.5rem, 5vw, 4.5rem); line-height: 0.92; letter-spacing: -0.07em; }
        .hero p { margin: 0; max-width: 480px; color: var(--muted); line-height: 1.75; }
        .hero ul { margin: 30px 0 0; padding: 0; list-style: none; display: grid; gap: 14px; }
        .hero li::before { content: "•"; color: var(--accent); font-weight: 900; margin-right: 10px; }
        .panel { padding: clamp(22px, 3vw, 34px); background: rgba(255,255,255,0.75); }
        .card { border-radius: 24px; padding: 24px; background: white; border: 1px solid var(--line); box-shadow: 0 18px 40px rgba(20, 33, 49, 0.08); }
        .card + .card { margin-top: 18px; }
        h2 { margin: 0 0 8px; font-size: 1.25rem; }
        .muted { color: var(--muted); line-height: 1.6; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .stack { display: grid; gap: 14px; margin-top: 20px; }
        label { font-size: 0.86rem; font-weight: 700; color: var(--muted); }
        input {
            width: 100%; margin-top: 8px; border: 1px solid var(--line-strong); border-radius: 14px;
            padding: 13px 14px; background: #fffefc; color: var(--ink); min-height: 48px;
            line-height: 1.35; box-shadow: inset 0 1px 2px rgba(25, 35, 46, 0.05);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            font-size: 1.3rem; letter-spacing: 0.18em; font-family: monospace; text-align: center;
        }
        input:focus { outline: none; border-color: rgba(15, 95, 102, 0.62); background: #ffffff; box-shadow: 0 0 0 4px rgba(15, 95, 102, 0.12); }
        button {
            border: 0; border-radius: 14px; padding: 13px 16px; min-height: 46px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: white;
            font-weight: 800; cursor: pointer; display: inline-flex; align-items: center;
            justify-content: center; line-height: 1.1;
        }
        .link-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--line); background: rgba(255,255,255,0.78); color: var(--ink); font-weight: 700; text-decoration: none; font-size: 0.9rem; }
        .alert { margin-bottom: 16px; padding: 14px 16px; border-radius: 16px; background: rgba(180, 35, 24, 0.08); color: #9d2d17; border: 1px solid rgba(180, 35, 24, 0.18); }
        .alert.success { background: rgba(21, 115, 71, 0.08); color: #1d6f46; border-color: rgba(21, 115, 71, 0.16); }
        .notice { padding: 12px 16px; border-radius: 14px; background: rgba(15, 95, 102, 0.07); border: 1px solid rgba(15, 95, 102, 0.14); font-size: 0.88rem; color: var(--ink); line-height: 1.55; margin-bottom: 18px; }
        @media (max-width: 920px) { .shell { grid-template-columns: 1fr; } .hero, .panel { padding: 26px; } }
        @media (max-width: 640px) { body { padding: 16px; } .shell { border-radius: 24px; } .card { border-radius: 20px; padding: 18px; } .hero h1 { font-size: clamp(2.05rem, 14vw, 3rem); } .actions > * { width: 100%; } .link-btn, button { width: 100%; } }
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <span>Two-Step Verification</span>
        <h1>Check Your Email</h1>
        <p>A 6-digit code was sent to the email address registered on this admin account.</p>
        <ul>
            <li>Code expires in 10 minutes</li>
            <li>Single-use only</li>
            <li>Do not share this code</li>
        </ul>
    </section>

    <section class="panel">
        @if ($errors->has('code') || $errors->has('auth'))
            <div class="alert">{{ $errors->first('code') ?: $errors->first('auth') }}</div>
        @endif

        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <h2>Enter Verification Code</h2>
            <div class="notice">
                We sent a 6-digit code to the email on this admin account.
                Enter it below to complete your sign-in.
            </div>
            <form method="post" action="{{ route('admin.2fa.verify') }}" class="stack">
                @csrf
                <div>
                    <label for="code">Verification Code</label>
                    <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" placeholder="000000" autocomplete="one-time-code" autofocus required>
                </div>
                <label for="trust_device" style="display:flex;gap:10px;align-items:flex-start;">
                    <input id="trust_device" type="checkbox" name="trust_device" value="1" style="width:auto;margin-top:2px;">
                    <span>
                        Trust this browser for 30 days.
                        <span class="muted" style="display:block;font-size:0.82rem;margin-top:4px;">If selected, we will not ask for another 2FA code on this browser for the next 30 days. <span style="color:#0f5f66;">[v3]</span></span>
                    </span>
                </label>
                <button type="submit">Verify &amp; Sign In</button>
            </form>
            <div class="actions">
                <form method="post" action="{{ route('admin.2fa.resend') }}" style="display:contents;">
                    @csrf
                    <button type="submit" class="link-btn" style="background:transparent;border-color:var(--line);">Resend Code</button>
                </form>
                <a class="link-btn" href="{{ route('admin.login') }}">Back To Login</a>
            </div>
        </div>
    </section>
</div>
</body>
</html>
