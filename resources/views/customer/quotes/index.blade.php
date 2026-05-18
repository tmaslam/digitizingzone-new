@extends('layouts.customer')

@section('title', 'My Quotes - '.$siteContext->displayLabel())
@section('hero_title', 'My Quotes')
@section('hero_text', 'Review quoted work, open the quote detail, and respond when pricing is ready.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Open Quotes</h3>
                <p>Quotes stay separate from live orders so you can review pricing first and switch only when you are ready.</p>
            </div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a class="button ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Download Quote List</a>
                <a class="button secondary" href="/quote.php">Start Digitizing Quote</a>
                <a class="button secondary" href="/vector-quote.php">Start Vector Quote</a>
            </div>
        </div>

        <div class="summary-grid" style="margin-bottom:16px;">
            <div class="action-card">
                <span>Total Quotes</span>
                <strong>{{ $quoteSummary['total'] }}</strong>
                <p>All quotes currently open under this account.</p>
            </div>
            <div class="action-card">
                <span>Ready For Response</span>
                <strong>{{ $quoteSummary['ready_for_response'] }}</strong>
                <p>Quotes that are ready for you to review and answer.</p>
            </div>
            <div class="action-card">
                <span>Feedback Pending</span>
                <strong>{{ $quoteSummary['feedback_pending'] }}</strong>
                <p>Quotes where your requested pricing feedback is already under review.</p>
            </div>
        </div>

        @if ($quotes->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Quote ID</th>
                        <th>Design Name</th>
                        <th>Type</th>
                        <th>Quoted Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($quotes as $quote)
                        <tr>
                            <td data-label="Quote ID">{{ $quote->order_id }}</td>
                            <td data-label="Type">
                                @php
                                    $quoteType = in_array((string) $quote->order_type, ['q-vector', 'qcolor'], true) ? 'Vector' : 'Digitizing';
                                @endphp
                                <span class="status {{ $quoteType === 'Vector' ? 'info' : 'success' }}">{{ $quoteType }}</span>
                            </td>
                            <td data-label="Design Name">
                                <strong>{{ $quote->design_name }}</strong>
                                <span class="table-note">{{ \App\Support\CustomerWorkflowStatus::actionHint($quote, true) }}</span>
                            </td>
                            <td data-label="Quoted Amount">{{ $quote->total_amount ?: $quote->stitches_price ?: '0.00' }}</td>
                            <td data-label="Status"><span class="status {{ \App\Support\CustomerWorkflowStatus::tone($quote, true) }}">{{ \App\Support\CustomerWorkflowStatus::label($quote, true) }}</span></td>
                            <td class="action-cell" data-label="Action">
                                <div class="action-group">
                                    <a class="button secondary" href="/view-quote-detail.php?order_id={{ $quote->order_id }}&origin=quotes">View Detail</a>
                                    <a class="button secondary" href="/view-quote-detail.php?order_id={{ $quote->order_id }}&origin=quotes#quote-response">Switch To Order</a>
                                    <form method="post" action="/quotes/{{ $quote->order_id }}/delete" onsubmit="return confirm('Delete this quote?');">
                                        @csrf
                                        <button type="submit" class="button danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $quotes->links() }}
            </div>
        @else
            <div class="empty-state">No quotes were found. Start a new quote any time you want pricing first.</div>
        @endif
    </section>
@endsection
