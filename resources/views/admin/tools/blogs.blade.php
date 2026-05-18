@extends('layouts.admin')

@php
    $currentColumn = request('column_name', 'id');
    $currentDirection = strtolower(request('sort', 'desc'));
    $nextDirection = fn ($column) => $currentColumn === $column && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

@section('title', 'Blogs | 1Dollar Admin')
@section('page_heading', 'Blogs')
@section('page_subheading', 'Blog listing against the existing `blogs` table.')

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'id', 'sort' => $nextDirection('id')]) }}">Blog ID</a></th>
                        <th>Blog Image</th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'title', 'sort' => $nextDirection('title')]) }}">Title</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'decription', 'sort' => $nextDirection('decription')]) }}">Description</a></th>
                        <th><a href="{{ request()->fullUrlWithQuery(['column_name' => 'date', 'sort' => $nextDirection('date')]) }}">Creation Date</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($blogs)->isEmpty())
                        <tr><td colspan="6" class="muted">No blog rows found.</td></tr>
                    @else
                    @foreach ($blogs as $blog)
                        <tr>
                            <td>
                                <form method="post" action="{{ url('/v/show-all-blogs/'.$blog->id.'/delete') }}" onsubmit="return confirm('Delete this blog?');">
                                    @csrf
                                    <button type="submit" style="background:linear-gradient(135deg,#a24d2a,#7f2e14);">Delete</button>
                                </form>
                            </td>
                            <td>{{ $blog->id }}</td>
                            <td>{{ $blog->attached_file ?: 'No image' }}</td>
                            <td>{{ $blog->title }}</td>
                            <td>{{ $blog->decription }}</td>
                            <td>{{ $blog->date }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($blogs->hasPages())
                <div style="margin-top:18px;">{{ $blogs->links() }}</div>
            @endif
        </div>
    </section>
@endsection
