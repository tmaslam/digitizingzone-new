@extends('layouts.customer')

@section('title', 'Credit & Invoice History - '.$siteContext->displayLabel())
@section('hero_title', 'Credit & Invoice History')
@section('hero_text', 'Referral credit and invoice credit usage remain visible inside the customer account while staying scoped to this site only.')

@section('content')
    <section class="content-card">
        <div class="metric-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="metric">
                <span>Referral Credit Earned</span>
                <strong>${{ number_format($referralTotal, 2) }}</strong>
            </div>
            <div class="metric">
                <span>Invoice Credit Used</span>
                <strong>${{ number_format($creditAppliedTotal, 2) }}</strong>
            </div>
        </div>
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Referral Credit</h3>
                <p>Referral activity shown here is filtered to accounts that belong to this website.</p>
            </div>
        </div>

        @if ($referralRows->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Referral Name</th>
                        <th>Transaction ID</th>
                        <th>Earned Credit</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($referralRows as $row)
                        <tr>
                            <td>{{ $row->referred_name }}</td>
                            <td>{{ $row->transaction_id }}</td>
                            <td>${{ number_format($row->credit, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">No referral credit activity is currently available for this site account.</div>
        @endif
    </section>

    <section class="content-card">
        <div class="section-head">
            <div>
                <h3>Invoice Credit Usage</h3>
                <p>These rows reflect billing records where credit was used on invoices tied to this site.</p>
            </div>
        </div>

        @if ($creditInvoiceRows->count())
            <div class="table-wrap responsive-stack">
                <table class="responsive-table">
                    <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Total Billing</th>
                        <th>Credit Used</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($creditInvoiceRows as $row)
                        <tr>
                            <td>{{ $row->transid }}</td>
                            <td>${{ number_format($row->total_billing, 2) }}</td>
                            <td>${{ number_format($row->credit_points, 2) }}</td>
                            <td>{{ $row->submitdate ?: '-' }}</td>
                            <td><a class="button ghost" href="/view-invoice-detail.php?transid={{ urlencode($row->transid) }}&origin=invoices">View Invoice</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">No invoice credit rows are currently available for this site account.</div>
        @endif
    </section>
@endsection
