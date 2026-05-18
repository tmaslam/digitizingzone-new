@extends('layouts.admin')

@section('title', 'Received Detail | 1Dollar Admin')
@section('page_heading', 'Received Detail For '.$customer->display_name)
@section('page_subheading', 'Paid invoice history for this customer.')

@section('content')
    <section class="card">
        <div class="card-body">
            @include('shared.admin-report-export', [
                'copy' => 'Download this customer received-detail report.',
                'label' => 'Download Report',
                'show' => $entries->count() > 0,
                'marginTop' => '0',
                'marginBottom' => '18px',
            ])
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:1.15rem;">{{ $customer->display_name }}</h3>
                    <p class="muted" style="margin:0;">Total paid amount across visible rows: {{ number_format((float) $sum, 2) }}</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="badge" href="{{ $source === 'payment-recieved' ? url('/v/payment-recieved.php') : url('/v/payment-recieved-report.php') }}">
                        Back to {{ $source === 'payment-recieved' ? 'Received Payment' : 'Payment Received Report' }}
                    </a>
                </div>
            </div>

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
                        <th>Transaction ID</th>
                        <th>Paid At</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if (collect($entries)->isEmpty())
                        <tr><td colspan="9" class="muted">No paid invoice rows found for this customer.</td></tr>
                    @else
                    @foreach ($entries as $entry)
                        @php
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
                            <td>{{ number_format((float) ($entry->order?->total_amount ?: $entry->amount), 2) }}</td>
                            <td>{{ $entry->transid ?: '-' }}</td>
                            <td>{{ $entry->trandtime ?: '-' }}</td>
                        </tr>
                    @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
