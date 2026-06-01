@extends('layouts.team')

@section('title', 'Summary | Digitizing Zone Team Portal')
@section('page_heading', 'Summary')
@section('page_subheading', ($teamUser->is_supervisor ?? false) ? 'Track team queues, review work, and move jobs forward without jumping through legacy screens.' : 'Jump into the right queue quickly and keep assigned work moving.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="stats">
                @foreach ($queueNavigation as $queue)
                    <a class="stat" href="{{ $queue['url'] }}">
                        <span class="muted">{{ $queue['label'] }}</span>
                        <strong>{{ $queue['count'] }}</strong>
                    </a>
                @endforeach
                @if ($teamUser->is_supervisor ?? false)
                    <a class="stat" href="{{ url('/team/review-queue.php') }}">
                        <span class="muted">Ready For Review</span>
                        <strong>{{ $navCounts['ready_review'] ?? 0 }}</strong>
                    </a>
                    <a class="stat" href="{{ url('/team/review-queue.php') }}">
                        <span class="muted">Verified Jobs</span>
                        <strong>{{ $navCounts['verified_jobs'] ?? 0 }}</strong>
                    </a>
                    <a class="stat" href="{{ url('/team/manage-team.php') }}">
                        <span class="muted">Team Members</span>
                        <strong>{{ $navCounts['team_members'] ?? 0 }}</strong>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <h3 style="margin:0 0 6px;font-size:1.15rem;">Queue Shortcuts</h3>
            <p class="muted" style="margin:0 0 18px;">Each queue now has its own stable route so the list, detail, and back actions stay in sync.</p>

            <div class="stats">
                @foreach ($queueNavigation as $queue)
                    <article class="stat" style="align-items:flex-start;">
                        <span class="muted">{{ $queue['label'] }}</span>
                        <strong>{{ $queue['count'] }}</strong>
                        <p class="muted" style="margin:8px 0 0;">{{ $queue['summary'] }}</p>
                        <a class="badge" href="{{ $queue['url'] }}" style="margin-top:12px;">Open Queue</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
