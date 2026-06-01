<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Digitizing Zone Team Portal')</title>
    <link rel="icon" type="image/png" href="{{ url('images/logo.png') }}">
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f0e8;
            --panel: rgba(255, 255, 255, 0.86);
            --ink: #14202b;
            --muted: #64707d;
            --line: rgba(20, 32, 43, 0.16);
            --line-strong: rgba(20, 32, 43, 0.28);
            --accent: #1e6a57;
            --accent-dark: #114439;
            --accent-soft: #dff1ea;
            --alert: #b26a2a;
            --shadow: 0 22px 54px rgba(20, 32, 43, 0.12);
        }

@include('shared.file-preview-styles')

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            color: var(--ink);
            overflow-x: hidden;
            background:
                radial-gradient(circle at top left, rgba(30, 106, 87, 0.12), transparent 34%),
                radial-gradient(circle at bottom right, rgba(178, 106, 42, 0.11), transparent 28%),
                linear-gradient(180deg, #faf8f3 0%, #efe7da 100%);
        }
        body.nav-open { overflow: hidden; }
        a { color: inherit; text-decoration: none; }
        .shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); min-height: 100vh; }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(16, 35, 47, 0.42);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
            z-index: 35;
        }
        .sidebar {
            padding: 24px 18px;
            background: rgba(16, 35, 47, 0.94);
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.08);
            position: sticky;
            top: 0;
            align-self: start;
            min-height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
            z-index: 40;
        }
        .sidebar-close,
        .mobile-nav-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 0;
            font-weight: 800;
            cursor: pointer;
            box-shadow: none;
        }
        .sidebar-close {
            background: rgba(255,255,255,0.14);
            color: #fff;
            margin-bottom: 14px;
        }
        .mobile-nav-toggle {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
        }
        .brand {
            padding: 18px;
            border-radius: 22px;
            background: rgba(255,255,255,0.08);
        }
        .brand h1 { margin: 0; font-size: 1.6rem; line-height: 1; letter-spacing: -0.04em; }
        .brand p { margin: 8px 0 0; color: rgba(255,255,255,0.72); font-size: 0.9rem; line-height: 1.6; }
        .section-title {
            margin: 22px 10px 10px;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: rgba(255,255,255,0.56);
            font-weight: 800;
        }
        .nav-card {
            background: rgba(255,255,255,0.05);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 10px;
        }
        .nav-card a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            color: rgba(255,255,255,0.88);
        }
        .nav-card a:hover,
        .nav-card a.active { background: rgba(255,255,255,0.1); }
        .count {
            min-width: 30px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(223, 241, 234, 0.14);
            color: #dff1ea;
            font-size: 0.8rem;
            text-align: center;
            font-weight: 800;
        }
        .main {
            padding: clamp(16px, 2vw, 24px);
            min-width: 0;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 18px 22px;
            background: rgba(255,255,255,0.64);
            border: 1px solid rgba(255,255,255,0.68);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }
        .topbar h2 { margin: 0; font-size: 1.58rem; letter-spacing: -0.04em; }
        .topbar p { margin: 6px 0 0; color: var(--muted); }
        .user-meta {
            text-align: right;
        }
        .user-meta strong {
            display: block;
        }
        .topbar-actions {
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(178, 106, 42, 0.14);
            color: #8a5320;
            font-weight: 800;
            white-space: nowrap;
        }
        .content {
            margin-top: 22px;
            display: grid;
            gap: 22px;
            min-width: 0;
        }
        .card {
            background: var(--panel);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.72);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-body { padding: clamp(16px, 2vw, 22px); }
        .card-body > h3,
        .card-body > h4 {
            margin: 0;
            letter-spacing: -0.02em;
        }
        .card-body > p {
            line-height: 1.65;
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 1px solid rgba(20, 32, 43, 0.1);
        }
        .section-copy {
            margin: 6px 0 0;
            max-width: 72ch;
            color: var(--muted);
            line-height: 1.65;
        }
        .action-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .stack {
            display: grid;
            gap: 16px;
        }
        .subcard,
        .content .card .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(244, 239, 229, 0.84));
            border: 1px solid rgba(20, 32, 43, 0.12);
            box-shadow: 0 14px 32px rgba(20, 32, 43, 0.07);
        }
        .subcard .card-body,
        .content .card .card .card-body {
            padding: clamp(14px, 1.8vw, 20px);
        }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .stat {
            padding: 18px;
            border-radius: 20px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.8);
        }
        .stat strong { display: block; font-size: 1.7rem; margin-top: 8px; }
        .muted { color: var(--muted); }
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid rgba(20, 32, 43, 0.12);
            border-radius: 18px;
            background: rgba(255,255,255,0.62);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.55);
        }
        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }
        th, td { padding: 13px 12px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
        th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgba(250, 248, 243, 0.95);
            backdrop-filter: blur(10px);
        }
        td { word-break: break-word; }
        tbody tr:nth-child(even) td {
            background: rgba(255,255,255,0.34);
        }
        tbody tr:hover td {
            background: rgba(223, 241, 234, 0.28);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-dark);
            font-size: 0.82rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 14px 16px;
            align-items: end;
            padding: 16px;
            border: 1px solid rgba(20, 32, 43, 0.12);
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(242, 238, 229, 0.74));
        }
        .field {
            display: grid;
            gap: 8px;
            min-width: 180px;
            flex: 1 1 220px;
            max-width: 360px;
        }
        label { font-size: 0.84rem; color: var(--muted); font-weight: 700; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.96);
            color: var(--ink);
            font: inherit;
            line-height: 1.4;
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
        }
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: rgba(30, 106, 87, 0.58);
            box-shadow: 0 0 0 4px rgba(30, 106, 87, 0.12);
        }
        button {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            min-height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }
        button,
        .badge {
            -webkit-tap-highlight-color: transparent;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(178, 106, 42, 0.12);
            color: #8a5320;
            border: 1px solid rgba(178, 106, 42, 0.18);
        }
        .pagination { display: flex; gap: 8px; flex-wrap: wrap; }
        .pagination-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .table-wrap th.action-col,
        .table-wrap td.action-col {
            min-width: 320px;
            width: 320px;
            white-space: normal;
            word-break: normal;
        }
        .table-wrap td.action-col {
            vertical-align: top;
        }
        .table-wrap td.action-col .action-row {
            display: inline-flex;
            flex-wrap: nowrap;
            gap: 8px;
            min-width: max-content;
            align-items: center;
        }
        .table-wrap td.action-col form {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            margin: 0;
        }
        .table-wrap td.action-col .badge,
        .table-wrap td.action-col button {
            margin: 0;
            white-space: nowrap;
        }
        .pagination-meta { color: var(--muted); font-size: 0.88rem; }
        .empty-state {
            padding: 18px;
            border: 1px dashed rgba(20, 32, 43, 0.2);
            border-radius: 18px;
            background: rgba(255,255,255,0.45);
            color: var(--muted);
        }
        @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 920px) {
            .shell { display: block; }
            .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(86vw, 300px);
                min-height: 100dvh;
                padding: 18px 16px;
                border-right: 1px solid rgba(255,255,255,0.12);
                border-bottom: 0;
                transform: translateX(-108%);
                transition: transform 0.24s ease;
                box-shadow: 0 26px 56px rgba(16, 35, 47, 0.34);
            }
            body.nav-open .sidebar { transform: translateX(0); }
            body.nav-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }
            .sidebar-close,
            .mobile-nav-toggle { display: inline-flex; }
            .main { padding: 18px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .user-meta { text-align: left; }
            .topbar-actions {
                justify-content: flex-start;
                width: 100%;
                flex-wrap: wrap;
            }
            .field {
                min-width: 0;
                max-width: none;
                flex-basis: calc(50% - 8px);
            }
            .nav-card a {
                padding: 12px;
            }
        }
        @media (max-width: 640px) {
            .stats { grid-template-columns: 1fr; }
            .main { padding: 14px; }
            .topbar { padding: 16px; border-radius: 20px; }
            .topbar h2 { font-size: 1.3rem; }
            .card { border-radius: 22px; }
            .card-body { padding: 16px; }
            .field { flex-basis: 100%; }
            .toolbar { gap: 12px; padding: 14px; }
            .topbar-actions,
            .pagination,
            .pagination-nav { width: 100%; }
            .table-wrap { border-radius: 16px; }
            table { min-width: 700px; }
            th, td { padding: 12px 10px; }
            .table-wrap th.action-col,
            .table-wrap td.action-col {
                min-width: 300px;
                width: 300px;
            }
            .pagination-nav { align-items: flex-start; }
            .section-head {
                margin-bottom: 16px;
                padding-bottom: 12px;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <button type="button" class="sidebar-close" data-mobile-nav-close>Close Menu</button>
        <div class="brand">
            <h1>Digitizing Zone<br>{{ ($teamUser->is_supervisor ?? false) ? 'Supervisor' : 'Team' }}</h1>
            <p>{{ ($teamUser->is_supervisor ?? false) ? 'Team oversight, assignment, uploads, notes, and completion updates.' : 'Assigned production work, uploads, notes, and completion updates.' }}</p>
        </div>

        <div class="section-title">Home</div>
        <div class="nav-card">
            <a href="{{ url('/team/welcome.php') }}" class="{{ request()->is('team/welcome.php') ? 'active' : '' }}">
                <span>Summary</span>
            </a>
        </div>

        @if ($teamUser->is_supervisor ?? false)
            <div class="section-title">Supervisor</div>
            <div class="nav-card">
                <a href="{{ url('/team/review-queue.php') }}" class="{{ request()->is('team/review-queue.php') ? 'active' : '' }}">
                    <span>Review Queue</span>
                    <span class="count">{{ $navCounts['ready_review'] ?? 0 }}</span>
                </a>
                <a href="{{ url('/team/manage-team.php') }}" class="{{ request()->is('team/manage-team.php') || request()->is('team/create-team.php') || request()->is('team/team-member-detail.php') ? 'active' : '' }}">
                    <span>Manage Team</span>
                    <span class="count">{{ $navCounts['team_members'] ?? 0 }}</span>
                </a>
            </div>
        @endif

        <div class="section-title">Production Queues</div>
        <div class="nav-card">
            @foreach (\App\Support\TeamWorkQueues::navigation($navCounts) as $queue)
                <a href="{{ $queue['url'] }}" class="{{ ($currentQueueKey ?? null) === $queue['key'] ? 'active' : '' }}">
                    <span>{{ $queue['label'] }}</span>
                    <span class="count">{{ $queue['count'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="section-title">Account</div>
        <div class="nav-card">
            <a href="{{ url('/team/logout.php') }}"><span>Log Out</span></a>
        </div>
    </aside>

    <main class="main">
        <section class="topbar">
            <div>
                <button type="button" class="mobile-nav-toggle" data-mobile-nav-toggle>Menu</button>
                <h2>@yield('page_heading', ($teamUser->is_supervisor ?? false) ? 'Supervisor Portal' : 'Team Portal')</h2>
                <p>@yield('page_subheading', 'Assigned work only.')</p>
            </div>
            <div class="user-meta">
                <strong>{{ $teamUser->display_name ?? $teamUser->user_name ?? 'Team' }}</strong><br>
                <span class="muted">{{ $teamUser->role_label ?? '' }} | {{ $teamUser->user_name ?? '' }}</span>
                <div class="topbar-actions">
                    <a class="badge" href="{{ url('/team/welcome.php') }}">Dashboard</a>
                    @if ($teamUser->is_supervisor ?? false)
                        <a class="badge" href="{{ url('/team/review-queue.php') }}">Review Queue</a>
                        <a class="badge" href="{{ url('/team/manage-team.php') }}">Manage Team</a>
                    @endif
                    @if (session('impersonator_admin_id'))
                        <form method="post" action="{{ url('/stop-simulated-session') }}">
                            @csrf
                            <button type="submit" class="badge" style="border:0;">Return To Admin</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="content">
            @if (session('impersonator_admin_id'))
                <div class="alert">You are viewing this portal as support for {{ session('impersonation_target_name', $teamUser->display_name ?? $teamUser->user_name) }}.</div>
            @endif
            @if (session('success'))
                <div class="alert">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </section>
    </main>
</div>
@include('shared.file-preview-modal')
<div class="sidebar-overlay" data-mobile-nav-overlay></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('[data-mobile-nav-toggle]');
    const close = document.querySelector('[data-mobile-nav-close]');
    const overlay = document.querySelector('[data-mobile-nav-overlay]');

    const closeMobileNav = function () {
        body.classList.remove('nav-open');
    };

    if (toggle) {
        toggle.addEventListener('click', function () {
            body.classList.toggle('nav-open');
        });
    }

    if (close) {
        close.addEventListener('click', closeMobileNav);
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileNav);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 920) {
                    closeMobileNav();
                }
            });
        });
    }

    if (!sidebar || !window.sessionStorage) {
        return;
    }

    const storageKey = 'onedollar-team-sidebar-scroll';
    const savedPosition = window.sessionStorage.getItem(storageKey);

    if (savedPosition !== null) {
        window.requestAnimationFrame(function () {
            sidebar.scrollTop = parseInt(savedPosition, 10) || 0;
        });
    }

    const persistSidebarScroll = function () {
        window.sessionStorage.setItem(storageKey, String(sidebar.scrollTop));
    };

    sidebar.addEventListener('scroll', persistSidebarScroll, { passive: true });
    window.addEventListener('beforeunload', persistSidebarScroll);
    document.querySelectorAll('a, form').forEach(function (element) {
        const eventName = element.tagName === 'FORM' ? 'submit' : 'click';
        element.addEventListener(eventName, persistSidebarScroll);
    });
});
</script>
@include('shared.file-preview-script')
</body>
</html>
