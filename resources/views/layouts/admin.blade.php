<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Digitizing Zone Admin')</title>
    <link rel="icon" type="image/png" href="{{ url('images/logo.png') }}">
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f1e7;
            --panel: rgba(255, 255, 255, 0.82);
            --panel-strong: #ffffff;
            --ink: #18222d;
            --muted: #6d7683;
            --line: rgba(24, 34, 45, 0.22);
            --line-strong: rgba(24, 34, 45, 0.34);
            --accent: #d62b2b;
            --accent-dark: #b01f1f;
            --accent-soft: #fdf0f0;
            --warning: #c56b22;
            --shadow: 0 24px 60px rgba(20, 33, 49, 0.12);
        }

@include('shared.file-preview-styles')

        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', 'Inter', 'Segoe UI', sans-serif;
            color: var(--ink);
            overflow-x: hidden;
            background:
                radial-gradient(circle at top left, rgba(214, 43, 43, 0.10), transparent 32%),
                radial-gradient(circle at top right, rgba(197, 107, 34, 0.12), transparent 26%),
                linear-gradient(180deg, #fbf7ef 0%, #f3eadb 100%);
        }
        body.nav-open { overflow: hidden; }
        a { color: inherit; text-decoration: none; }
        .shell { display: grid; grid-template-columns: 300px minmax(0, 1fr); min-height: 100vh; }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(18, 32, 46, 0.42);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
            z-index: 35;
        }
        .sidebar {
            padding: 28px 20px;
            border-right: 1px solid rgba(255,255,255,0.5);
            background: rgba(13, 30, 46, 0.9);
            color: white;
            backdrop-filter: blur(16px);
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
            border: 0;
            border-radius: 999px;
            padding: 10px 14px;
            min-height: 40px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: none;
        }
        .sidebar-close {
            background: rgba(255,255,255,0.16);
            margin-bottom: 14px;
        }
        .mobile-nav-toggle {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        }
        .brand {
            padding: 18px 18px 22px;
            border-radius: 24px;
            background: linear-gradient(145deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .brand h1 { margin: 0; font-size: 1.7rem; line-height: 0.95; letter-spacing: -0.05em; }
        .brand p { margin: 10px 0 0; color: rgba(255,255,255,0.7); font-size: 0.92rem; line-height: 1.6; }
        .section-title {
            margin: 26px 10px 10px;
            font-size: 0.72rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
            font-weight: 800;
        }
        .nav-list { display: grid; gap: 10px; }
        .nav-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 12px;
        }
        .nav-card a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            color: rgba(255,255,255,0.88);
            transition: background 0.2s ease;
        }
        .nav-card a:hover, .nav-card a.active { background: rgba(255,255,255,0.12); }
        .count {
            min-width: 32px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(253, 240, 240, 0.14);
            color: #fdf0f0;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 800;
        }
        .main {
            padding: clamp(16px, 2.2vw, 28px);
            min-width: 0;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            padding: 18px 22px;
            background: rgba(255,255,255,0.58);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 24px;
            backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }
        .topbar-copy {
            min-width: 0;
            max-width: 60rem;
        }
        .topbar h2 { margin: 0; font-size: 1.85rem; line-height: 1.08; letter-spacing: -0.04em; }
        .topbar p { margin: 8px 0 0; max-width: 48rem; color: var(--muted); line-height: 1.6; }
        .user-meta { text-align: right; }
        .user-meta strong { display: block; }
        .topbar-actions {
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .logout {
            display: inline-flex;
            padding: 10px 14px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            font-weight: 800;
        }
        .content {
            margin-top: 22px;
            display: grid;
            gap: 22px;
            min-width: 0;
        }
        .card {
            background: var(--panel);
            border: 1px solid rgba(255,255,255,0.66);
            border-radius: 26px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
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
            border-bottom: 1px solid rgba(24, 34, 45, 0.1);
        }
        .section-head h3 {
            margin: 0;
            font-size: 1.28rem;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }
        .section-head:last-child {
            margin-bottom: 0;
        }
        .section-copy {
            margin: 6px 0 0;
            max-width: 74ch;
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
            background: linear-gradient(180deg, rgba(255,255,255,0.9), rgba(247, 243, 234, 0.82));
            border: 1px solid rgba(24, 34, 45, 0.12);
            box-shadow: 0 14px 34px rgba(20, 33, 49, 0.08);
        }
        .subcard .card-body,
        .content .card .card .card-body {
            padding: clamp(14px, 1.8vw, 20px);
        }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .workflow-focus-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .stat-link {
            display: block;
            color: inherit;
            min-width: 0;
        }
        .stat-link .stat {
            height: 100%;
            min-width: 0;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .stat-link:hover .stat {
            transform: translateY(-2px);
            border-color: rgba(214, 43, 43, 0.22);
            box-shadow: 0 18px 34px rgba(20, 33, 49, 0.1);
        }
        .stat {
            padding: 18px;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(255,255,255,0.56));
            border: 1px solid var(--line);
        }
        .stat > .muted:first-child {
            display: block;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .stat strong {
            display: block;
            margin-top: 10px;
            font-size: 1.45rem;
            line-height: 1.08;
            letter-spacing: -0.04em;
        }
        .stat > .muted:not(:first-child) {
            font-size: 0.9rem;
            line-height: 1.55;
            color: var(--muted);
        }
        .stat > strong + .muted {
            margin-top: 8px;
        }
        .stat > .muted:last-child {
            margin-top: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent-dark);
        }
        .muted { color: var(--muted); }
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid rgba(24, 34, 45, 0.12);
            border-radius: 18px;
            background: rgba(255,255,255,0.62);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.55);
        }
        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
        th {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgba(250, 246, 239, 0.95);
            backdrop-filter: blur(10px);
        }
        td {
            word-break: break-word;
        }
        .cell-nowrap {
            white-space: nowrap;
        }
        .cell-wrap-md {
            max-width: 220px;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .cell-wrap-lg {
            max-width: 320px;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        tbody tr:nth-child(even) td {
            background: rgba(255,255,255,0.34);
        }
        tbody tr:hover td {
            background: rgba(253, 240, 240, 0.35);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            min-height: 36px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-dark);
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 14px 16px;
            align-items: end;
            padding: 16px;
            border: 1px solid rgba(24, 34, 45, 0.12);
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(244, 238, 228, 0.72));
        }
        .field {
            display: grid;
            gap: 8px;
            min-width: 180px;
            flex: 1 1 220px;
            max-width: 320px;
        }
        .filter-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 16px;
            align-items: end;
            padding: 16px;
            border: 1px solid rgba(24, 34, 45, 0.12);
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(244, 238, 228, 0.72));
        }
        .filter-grid label {
            display: grid;
            gap: 6px;
            min-width: 160px;
            flex: 1 1 200px;
            max-width: 280px;
            font-weight: 700;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.3;
        }
        .filter-grid > div {
            flex: 0 0 auto;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        label { font-size: 0.84rem; color: var(--muted); font-weight: 700; line-height: 1.3; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="search"],
        input[type="date"],
        input[type="datetime-local"],
        input[type="file"],
        select {
            width: 100%;
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.96);
            color: var(--ink);
            box-shadow: inset 0 1px 2px rgba(24, 34, 45, 0.04);
            min-height: 46px;
            line-height: 1.35;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="search"],
        select {
            appearance: none;
        }
        input[type="file"] {
            padding: 10px 12px;
            cursor: pointer;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="search"]:focus,
        input[type="date"]:focus,
        input[type="datetime-local"]:focus,
        input[type="file"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: rgba(214, 43, 43, 0.50);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(214, 43, 43, 0.10);
        }
        input[type="checkbox"],
        input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }
        button,
        a.button,
        .button {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            min-height: 44px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            line-height: 1.1;
            box-shadow: 0 12px 24px rgba(214, 43, 43, 0.16);
            transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
            white-space: nowrap;
            text-decoration: none;
            font-size: inherit;
        }
        button:hover,
        a.button:hover,
        .button:hover {
            filter: brightness(1.02);
            transform: translateY(-1px);
        }
        button.secondary,
        a.button.secondary,
        .button.secondary {
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(253,240,240,0.55));
            color: var(--accent);
            box-shadow: 0 2px 8px rgba(214, 43, 43, 0.08);
            border: 1px solid rgba(214, 43, 43, 0.18);
        }
        button,
        a.button,
        .badge,
        .logout {
            -webkit-tap-highlight-color: transparent;
        }
        .pagination { display: flex; gap: 8px; flex-wrap: wrap; }
        .pagination-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination-meta {
            color: var(--muted);
            font-size: 0.88rem;
        }
        .pagination a, .pagination span {
            min-width: 38px;
            min-height: 38px;
            padding: 8px 11px;
            border-radius: 10px;
            background: rgba(255,255,255,0.86);
            border: 1px solid var(--line);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .pagination a:hover {
            border-color: rgba(214, 43, 43, 0.28);
            background: #ffffff;
        }
        .pagination .current {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border-color: transparent;
            color: #ffffff;
        }
        .pagination .disabled {
            opacity: 0.46;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(197, 107, 34, 0.12);
            color: #8c4f18;
            border: 1px solid rgba(197, 107, 34, 0.18);
        }
        textarea {
            width: 100%;
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.96);
            color: var(--ink);
            font: inherit;
            line-height: 1.45;
            box-shadow: inset 0 1px 2px rgba(24, 34, 45, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .table-wrap td {
            vertical-align: middle;
        }
        .table-wrap th.action-col,
        .table-wrap td.action-col {
            min-width: 340px;
            width: 340px;
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
            flex-wrap: nowrap;
        }
        .table-wrap td form {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .table-wrap td .badge,
        .table-wrap td button {
            margin: 0;
        }
        .table-wrap td > div[style*="display:flex"] {
            align-items: center;
        }
        .empty-state {
            padding: 18px;
            border: 1px dashed rgba(24, 34, 45, 0.2);
            border-radius: 18px;
            background: rgba(255,255,255,0.45);
            color: var(--muted);
        }
        @media (max-width: 1200px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .workflow-focus-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 960px) {
            .shell { display: block; }
            .sidebar-overlay { display: block; }
            .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(86vw, 320px);
                min-height: 100dvh;
                max-height: 100dvh;
                border-right: 1px solid rgba(255,255,255,0.12);
                border-bottom: 0;
                padding: 20px 16px;
                transform: translateX(-108%);
                transition: transform 0.24s ease;
                box-shadow: 0 28px 60px rgba(18, 32, 46, 0.34);
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
            .topbar-copy { max-width: none; }
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
            .workflow-focus-grid { grid-template-columns: 1fr; }
            .main { padding: 14px; }
            .topbar { padding: 16px; border-radius: 20px; }
            .topbar h2 { font-size: 1.45rem; }
            .topbar p { font-size: 0.95rem; }
            .section-head h3 { font-size: 1.12rem; }
            .stat strong { font-size: 1.28rem; }
            .card { border-radius: 22px; }
            .card-body { padding: 16px; }
            .field {
                flex-basis: 100%;
            }
            .toolbar,
            .filter-grid {
                gap: 12px;
                padding: 14px;
            }
            .filter-grid label {
                flex-basis: 100%;
                max-width: 100%;
            }
            .filter-grid > div {
                flex-basis: 100%;
                flex-wrap: wrap;
            }
            .topbar-actions,
            .pagination,
            .pagination-nav {
                width: 100%;
            }
            .table-wrap {
                border-radius: 16px;
            }
            table {
                min-width: 700px;
            }
            th, td {
                padding: 12px 10px;
            }
            .table-wrap th.action-col,
            .table-wrap td.action-col {
                min-width: 320px;
                width: 320px;
            }
            .pagination-nav {
                align-items: flex-start;
            }
            .section-head {
                margin-bottom: 16px;
                padding-bottom: 12px;
            }
        }
    </style>
</head>
<body>
@php
    $originContext = (string) request('back', request('source', ''));
    $queueContext = (string) (request()->route('queue') ?? ($originContext !== '' ? $originContext : request('queue', request('page', ''))));
    $activeQueue = \App\Support\AdminOrderQueues::match($queueContext);
    if ($activeQueue === null && request()->route('queue')) {
        $activeQueue = \App\Support\AdminOrderQueues::normalize((string) request()->route('queue'));
    }
    $reportContext = strtolower(trim($originContext));
    $activeCustomers = (
        request()->is('v/customer_list.php')
        || request()->is('v/customer-detail.php')
        || request()->is('v/edit-customer-detail.php')
    ) && $reportContext !== 'customer-approvals';
    $activeCustomerApprovals = request()->is('v/customer-approvals.php')
        || ((request()->is('v/customer-detail.php') || request()->is('v/edit-customer-detail.php')) && $reportContext === 'customer-approvals');
    $activeTeams = request()->is('v/show-all-teams.php')
        || request()->is('v/create-teams.php');
    $activeBusinessBilling = match (true) {
        request()->is('v/all-payment-due.php'),
        request()->is('v/payment-due-detail.php') && $reportContext === 'all-payment-due',
        $reportContext === 'all-payment-due' => 'due',
        request()->is('v/payment-recieved.php'),
        request()->is('v/payment-recieved-detail.php') && $reportContext === 'payment-recieved',
        $reportContext === 'payment-recieved' => 'received',
        default => null,
    };
    $activeBillingReport = match (true) {
        request()->is('v/payment-due-report.php'),
        request()->is('v/payment-due-detail.php') && $reportContext === 'payment-due-report',
        $reportContext === 'payment-due-report' => 'due',
        request()->is('v/payment-recieved-report.php'),
        request()->is('v/payment-recieved-detail.php') && $reportContext === 'payment-recieved-report',
        $reportContext === 'payment-recieved-report' => 'received',
        default => null,
    };
    $activeTeamReport = request()->is('v/monthly-reports.php');
    $activeLoginHistory = request()->is('v/login_history.php');
    $activeSecurityEvents = request()->is('v/security-events.php');
    $activeBlockedCustomers = request()->is('v/block-customer_list.php');
    $activeBlockedIps = request()->is('v/blocked-ip-list.php') || request()->is('v/block_ip.php');
    $orderQueueNav = \App\Support\AdminOrderQueues::navigation($navCounts ?? [], 'orders');
    $quoteQueueNav = \App\Support\AdminOrderQueues::navigation($navCounts ?? [], 'quotes');
@endphp
<div class="shell">
    <aside class="sidebar">
        <button type="button" class="sidebar-close" data-mobile-nav-close>Close Menu</button>
        <div class="brand">
            <h1>Digitizing Zone<br>Admin</h1>
            <p>Management portal for orders, quotes, billing, customers, teams, and reports.</p>
        </div>

        <div class="section-title">Home</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/welcome.php') }}" class="{{ request()->is('v/welcome.php') ? 'active' : '' }}">
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <div class="section-title">Order Management</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/create-order.php') }}" class="{{ request()->is('v/create-order.php') ? 'active' : '' }}"><span>Create Order / Quote</span></a>
                @foreach ($orderQueueNav as $queueItem)
                    <a href="{{ $queueItem['url'] }}" class="{{ (request()->is('v/orders/*') || request()->is('v/orders.php')) && $activeQueue !== null && $activeQueue === $queueItem['key'] ? 'active' : '' }}"><span>{{ $queueItem['label'] === 'Approved Orders' ? 'Approved / Unpaid' : $queueItem['label'] }}</span><span class="count">{{ $queueItem['count'] }}</span></a>
                @endforeach
            </div>
        </div>

        <div class="section-title">Quotes</div>
        <div class="nav-list">
            <div class="nav-card">
                @foreach ($quoteQueueNav as $queueItem)
                    <a href="{{ $queueItem['url'] }}" class="{{ (request()->is('v/orders/*') || request()->is('v/orders.php')) && $activeQueue !== null && $activeQueue === $queueItem['key'] ? 'active' : '' }}"><span>{{ $queueItem['label'] === 'Designer Completed Quotes' ? 'Designer Completed' : $queueItem['label'] }}</span><span class="count">{{ $queueItem['count'] }}</span></a>
                @endforeach
            </div>
        </div>

        <div class="section-title">Business</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/all-payment-due.php') }}" class="{{ $activeBusinessBilling === 'due' ? 'active' : '' }}"><span>Due Payment</span><span class="count">{{ $navCounts['due_payments'] ?? 0 }}</span></a>
                <a href="{{ url('/v/payment-recieved.php') }}" class="{{ $activeBusinessBilling === 'received' ? 'active' : '' }}"><span>Received Payment</span><span class="count">{{ $navCounts['received_payments'] ?? 0 }}</span></a>
                <a href="{{ url('/v/customer_list.php') }}" class="{{ $activeCustomers ? 'active' : '' }}"><span>Customers</span><span class="count">{{ $navCounts['customers'] ?? 0 }}</span></a>
                <a href="{{ url('/v/customer-approvals.php') }}" class="{{ $activeCustomerApprovals ? 'active' : '' }}"><span>Customer Approvals</span><span class="count">{{ $navCounts['pending_customer_approvals'] ?? 0 }}</span></a>
                <a href="{{ url('/v/show-all-teams.php') }}" class="{{ $activeTeams ? 'active' : '' }}"><span>Teams</span><span class="count">{{ $navCounts['teams'] ?? 0 }}</span></a>
            </div>
        </div>

        <div class="section-title">Reports</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/payment-due-report.php') }}" class="{{ $activeBillingReport === 'due' ? 'active' : '' }}"><span>Payment Due Report</span></a>
                <a href="{{ url('/v/payment-recieved-report.php') }}" class="{{ $activeBillingReport === 'received' ? 'active' : '' }}"><span>Payment Received Report</span></a>
                <a href="{{ url('/v/monthly-reports.php') }}" class="{{ $activeTeamReport ? 'active' : '' }}"><span>Team Report</span></a>
                <a href="{{ url('/v/login_history.php') }}" class="{{ $activeLoginHistory ? 'active' : '' }}"><span>Login History</span></a>
            </div>
        </div>

        <div class="section-title">Security</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/security-events.php') }}" class="{{ $activeSecurityEvents ? 'active' : '' }}"><span>Security Events</span><span class="count">{{ $navCounts['security_alerts'] ?? 0 }}</span></a>
                <a href="{{ url('/v/block-customer_list.php') }}" class="{{ $activeBlockedCustomers ? 'active' : '' }}"><span>Inactive Customers</span><span class="count">{{ $navCounts['blocked_customers'] ?? 0 }}</span></a>
                <a href="{{ url('/v/blocked-ip-list.php') }}" class="{{ $activeBlockedIps ? 'active' : '' }}"><span>Blocked IPs</span></a>
            </div>
        </div>

        <div class="section-title">Extras</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/notify-customers.php') }}" class="{{ request()->is('v/notify-customers.php') ? 'active' : '' }}"><span>Notify Customers</span></a>
                <a href="{{ url('/v/email-templates.php') }}" class="{{ request()->is('v/email-templates.php') || request()->is('v/email-templates-create.php') || request()->is('v/email-templates/*/edit') ? 'active' : '' }}"><span>Email Templates</span></a>
                <a href="{{ url('/v/site-payments.php') }}" class="{{ request()->is('v/site-payments.php') || request()->is('v/site-payments/*/edit') ? 'active' : '' }}"><span>Site Payments</span></a>
                <a href="{{ url('/v/site-pricing.php') }}" class="{{ request()->is('v/site-pricing.php') || request()->is('v/site-pricing-create.php') || request()->is('v/site-pricing/*/edit') ? 'active' : '' }}"><span>Site Pricing</span></a>
                <a href="{{ url('/v/site-offers.php') }}" class="{{ request()->is('v/site-offers.php') || request()->is('v/site-offers-create.php') || request()->is('v/site-offers/*/edit') ? 'active' : '' }}"><span>Site Offers</span></a>
                <a href="{{ url('/v/offer-claims.php') }}" class="{{ request()->is('v/offer-claims.php') || request()->is('v/site-offers/*/claims') ? 'active' : '' }}"><span>Offer Claims</span></a>
                <a href="{{ url('/v/transaction-history.php') }}" class="{{ request()->is('v/transaction-history.php') || request()->is('v/pay-now.php') ? 'active' : '' }}"><span>Transactions</span></a>
                <a href="{{ url('/v/customer-payment-inventory.php') }}" class="{{ request()->is('v/customer-payment-inventory.php') ? 'active' : '' }}"><span>Customer Payment Inventory</span></a>
            </div>
        </div>

        <div class="section-title">Admin</div>
        <div class="nav-list">
            <div class="nav-card">
                <a href="{{ url('/v/change-password.php') }}" class="{{ request()->is('v/change-password.php') ? 'active' : '' }}"><span>Change Password</span></a>
                <a href="{{ url('/v/logout.php') }}"><span>Log Out</span></a>
            </div>
        </div>
    </aside>

    <main class="main">
        <section class="topbar">
            <div class="topbar-copy">
                <button type="button" class="mobile-nav-toggle" data-mobile-nav-toggle>Menu</button>
                <h2>@yield('page_heading', 'Admin Panel')</h2>
                <p>@yield('page_subheading', 'Review and manage daily admin operations.')</p>
            </div>
            <div class="user-meta">
                <strong>{{ $adminUser->display_name ?? $adminUser->user_name ?? 'Admin' }}</strong>
                <span class="muted">{{ $adminUser->user_name ?? '' }}</span>
                <div class="topbar-actions">
                    <a class="badge" href="{{ url('/v/welcome.php') }}">Dashboard</a>
                    @if (session('impersonator_admin_id'))
                        <form method="post" action="{{ url('/stop-simulated-session') }}">
                            @csrf
                            <button type="submit" class="badge" style="border:0;">Return To Admin</button>
                        </form>
                    @endif
                    <a class="logout" href="{{ url('/v/logout.php') }}">Log Out</a>
                </div>
            </div>
        </section>

        <section class="content">
            @if (session('impersonator_admin_id'))
                <div class="alert">You are currently inside a simulated admin session for {{ session('impersonation_target_name', $adminUser->display_name ?? $adminUser->user_name) }}.</div>
            @endif
            @if (session('success'))
                <div class="alert">{{ session('success') }}</div>
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
                if (window.innerWidth <= 960) {
                    closeMobileNav();
                }
            });
        });
    }

    if (!sidebar || !window.sessionStorage) {
        return;
    }

    const storageKey = 'onedollar-admin-sidebar-scroll';
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
