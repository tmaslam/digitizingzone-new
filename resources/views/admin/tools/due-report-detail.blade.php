@extends('layouts.admin')

@section('title', 'Due Detail | 1Dollar Admin')
@section('page_heading', 'Due Detail For '.$customer->display_name)
@section('page_subheading', 'Review unpaid invoices and choose either external payment recording or customer-credit application.')

@section('content')
    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">{{ $customer->display_name }}</h3>
                    <p class="muted" style="margin:0;">Total amount across current unpaid rows: {{ number_format((float) $sum, 2) }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ $source === 'all-payment-due' ? url('/v/all-payment-due.php') : url('/v/payment-due-report.php') }}">
                        Back to {{ $source === 'all-payment-due' ? 'Due Payment' : 'Payment Due Report' }}
                    </a>
                </div>
            </div>

            <div class="stats" style="margin-top:18px;">
                <article class="stat"><span class="muted">Unpaid Total</span><strong>{{ number_format((float) $sum, 2) }}</strong></article>
                <article class="stat"><span class="muted">Available Balance</span><strong>{{ number_format((float) $currentBalance, 2) }}</strong></article>
            </div>

            <div class="alert" style="margin-top:18px;">
                <strong>Action guide:</strong>
                Record External Payment marks invoice(s) paid without reducing customer credit.
                Use Customer Credit marks invoice(s) paid and deducts the amount from available balance.
            </div>

            <form method="post" action="{{ url('/v/payment-due-report/customer-pay') }}" style="margin-top:18px;" class="toolbar">
                @csrf
                <input type="hidden" name="uid" value="{{ $customer->user_id }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <div class="field">
                    <label for="transaction_id_all">Transaction ID For External Payment</label>
                    <input id="transaction_id_all" type="text" name="transaction_id">
                </div>
                <div class="field" style="min-width:auto;">
                    <label>&nbsp;</label>
                    <button type="submit">Record External Payment For All</button>
                </div>
            </form>
            @include('shared.admin-report-export', [
                'copy' => 'Download this customer due-detail report.',
                'label' => 'Download Report',
                'show' => $entries->count() > 0,
                'marginTop' => '12px',
                'marginBottom' => '0',
            ])

            @if ($currentBalance > 0)
                <form method="post" action="{{ url('/v/payment-due-report/customer/apply-balance') }}" style="margin-top:12px;" class="toolbar">
                    @csrf
                    <input type="hidden" name="uid" value="{{ $customer->user_id }}">
                    <input type="hidden" name="source" value="{{ $source }}">
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Use Customer Credit For Due Invoices</button>
                    </div>
                </form>
            @endif

            <div class="table-wrap" style="margin-top:18px;">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Bill ID</th>
                        <th>Order ID</th>
                        <th>Design Name</th>
                        <th>Completion</th>
                        <th>Stitches</th>
                        <th>Total Amount</th>
                        <th>Pay</th>
                        <th>Use Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($entries)->isEmpty())
                        <tr><td colspan="9" class="muted">No unpaid invoice rows found for this customer.</td></tr>
                    @else
                    @foreach ($entries as $entry)
                        @php
                            $entryAmount = (float) ($entry->order?->total_amount ?: $entry->amount);
                            $order = $entry->order;
                            $detailUrl = null;
                            $detailLabel = 'Open Order';

                            if ($order) {
                                $detailUrl = match ((string) $order->order_type) {
                                    'qquote' => url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'),
                                    'quote', 'digitzing', 'qcolor' => url('/v/orders/'.$order->order_id.'/detail/quote?back='.rawurlencode($source)),
                                    'vector', 'q-vector' => url('/v/orders/'.$order->order_id.'/detail/vector?back='.rawurlencode($source)),
                                    default => url('/v/orders/'.$order->order_id.'/detail/order?back='.rawurlencode($source)),
                                };
                            } elseif ($entry->order_id) {
                                $detailUrl = url('/v/orders/'.$entry->order_id.'/detail/order?back='.rawurlencode($source));
                            } elseif ($entry->user_id) {
                                $detailUrl = url('/v/customer-detail.php?uid='.$entry->user_id);
                                $detailLabel = 'Open Customer';
                            }
                        @endphp
                        <tr>
                            <td>
                                @if ($detailUrl)
                                    <a class="badge" href="{{ $detailUrl }}">{{ $detailLabel }}</a>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>#{{ $entry->bill_id }}</td>
                            <td>{{ $entry->order_id }}</td>
                            <td>{{ $entry->order?->design_name ?: '-' }}</td>
                            <td>{{ $entry->order?->completion_date ?: '-' }}</td>
                            <td>{{ $entry->order?->stitches ?: '-' }}</td>
                            <td>{{ number_format($entryAmount, 2) }}</td>
                            <td>
                                <form method="post" action="{{ url('/v/payment-due-report/invoice/'.$entry->bill_id.'/pay') }}" class="toolbar">
                                    @csrf
                                    <input type="hidden" name="source" value="{{ $source }}">
                                    <div class="field">
                                        <label>Transaction ID</label>
                                        <input type="text" name="transaction_id">
                                    </div>
                                    <div class="field" style="min-width:auto;">
                                        <label>&nbsp;</label>
                                        <button type="submit">Record External Payment</button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                @if ($currentBalance >= $entryAmount && $entryAmount > 0)
                                    <form method="post" action="{{ url('/v/payment-due-report/invoice/'.$entry->bill_id.'/apply-balance') }}" onsubmit="return confirm('Apply available customer balance to this invoice?');">
                                        @csrf
                                        <input type="hidden" name="source" value="{{ $source }}">
                                        <button type="submit">Use Customer Credit</button>
                                    </form>
                                @else
                                    <span class="muted">Not enough balance</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>

            @if ($balanceEntries->isNotEmpty())
                <div class="table-wrap" style="margin-top:18px;">
                    <table>
                        <thead>
                        <tr>
                            <th>Balance Activity</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Date Added</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($balanceEntries as $entry)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $entry->entry_type)) }}</td>
                                <td>{{ number_format((float) $entry->amount, 2) }}</td>
                                <td>{{ $entry->reference_no ?: '-' }}</td>
                                <td>{{ $entry->date_added ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
