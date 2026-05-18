@extends('layouts.customer')

@section('title', 'My Invoices - '.$siteContext->displayLabel())
@section('hero_title', 'My Invoices')
@section('hero_text', 'Review your paid invoice history and open past payment details anytime.')

@section('content')
    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Invoice History</h3>
                <p>Each transaction groups the paid billing rows that were settled together.</p>
            </div>
            <a class="button ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Download Invoice List</a>
        </div>

        <form method="get" action="/view-invoices.php" class="invoice-filterbar">
            <label class="filter-field">
                <span class="field-label">Date From</span>
                <input type="date" name="date_from" value="{{ $dateFrom }}">
            </label>
            <label class="filter-field">
                <span class="field-label">Date To</span>
                <input type="date" name="date_to" value="{{ $dateTo }}">
            </label>
            <div class="field-actions">
                <button type="submit">Filter</button>
                <a class="button secondary" href="/view-invoices.php">Reset</a>
            </div>
            <div class="field-hint">
                @if (! empty($usingDefaultRange))
                    @if (\Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo)) <= 90 && \Carbon\Carbon::parse($dateTo)->dayOfYear <= 90)
                        Showing the last 90 days by default for a better new-year view. Change the range as needed.
                    @else
                        Showing {{ \Carbon\Carbon::parse($defaultFrom)->format('Y') }} invoices by default. Change the range as needed.
                    @endif
                @else
                    Use the date range above to review invoice history for any period you need.
                @endif
            </div>
        </form>

        @if ($invoiceGroups->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Transaction ID</th>
                        <th>Total Designs</th>
                        <th>Date</th>
                        <th>Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoiceGroups as $invoice)
                        <tr>
                            <td><a class="button secondary" href="/view-invoice-detail.php?transid={{ urlencode($invoice->transid) }}&origin=invoices">View</a></td>
                            <td>{{ $invoice->transid }}</td>
                            <td>{{ $invoice->total_designs }}</td>
                            <td>{{ $invoice->invoice_date ?: '-' }}</td>
                            <td>${{ number_format((float) $invoice->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                {{ $invoiceGroups->links() }}
            </div>
        @else
            <div class="empty-state">No paid invoices are currently available.</div>
        @endif
    </section>
@endsection
