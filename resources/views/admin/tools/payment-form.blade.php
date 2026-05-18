@extends('layouts.admin')

@section('title', 'Payment Entry | 1Dollar Admin')
@section('page_heading', $payment ? 'Edit Payment' : 'New Payment')
@section('page_subheading', 'Manual transaction entry for the customer payment ledger.')

@section('content')
    @unless ($hasPaymentsTable)
        <div class="alert">The `customerpayments` table was not found in this database, so this form cannot save transactions here.</div>
    @else
        @if ($errors->any())
            <div class="alert">{{ $errors->first() }}</div>
        @endif

        <section class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;">
                    <div class="muted">
                        {{ $payment ? 'You are editing an existing payment record.' : 'Create a new payment and optionally apply it to unpaid invoices.' }}
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a class="badge" href="{{ url('/v/pay-now.php') }}">New Blank Payment</a>
                        @if ($customer)
                            <a class="badge" href="{{ url('/v/pay-now.php?user_id='.$customer->user_id) }}">New Payment For This Customer</a>
                        @endif
                        <a class="badge" href="{{ url('/v/transaction-history.php') }}">Back To Transactions</a>
                    </div>
                </div>

                @if ($customer)
                    <div class="alert" style="margin-bottom:18px;">
                        Customer: <strong>{{ $customer->display_name }}</strong>{{ $customer->user_email ? ' | '.$customer->user_email : '' }} | Current unpaid total: <strong>{{ number_format((float) $dueTotal, 2) }}</strong>
                    </div>
                @endif

                @if ($paymentSummary)
                    <div class="alert" style="margin-bottom:18px;">
                        This payment is currently tracked as <strong>{{ $paymentSummary['status'] }}</strong>.
                        Applied to invoices: <strong>{{ number_format((float) $paymentSummary['applied_amount'], 2) }}</strong>.
                        Remaining balance: <strong>{{ number_format((float) $paymentSummary['balance_amount'], 2) }}</strong>.
                    </div>
                @endif

                <form method="post" action="{{ url('/v/pay-now.php') }}" class="toolbar">
                    @csrf
                    @if ($payment)
                        <input type="hidden" name="Seq_No" value="{{ $payment->Seq_No }}">
                    @endif
                    <div class="field" style="max-width:none;position:relative;">
                        <label>Find Customer</label>
                        <input
                            type="text"
                            id="customer_lookup"
                            autocomplete="off"
                            placeholder="Search by user ID, username, email, or name"
                            value="{{ $customer ? trim(implode(' | ', array_filter([$customer->display_name, $customer->user_name ? '@'.$customer->user_name : null, $customer->user_email]))) : '' }}"
                        >
                        <span class="muted">This helps fill the customer user ID. Payment rules and save behavior stay the same.</span>
                        <div id="customer_lookup_results" class="card subcard" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;margin-top:8px;">
                            <div class="card-body" style="padding:8px;">
                                <div id="customer_lookup_results_inner"></div>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Payment Amount</label>
                        <input type="number" step="0.01" min="0" inputmode="decimal" name="Payment_Amount" value="{{ old('Payment_Amount', $payment?->Payment_Amount ?? $amount) }}">
                    </div>
                    <div class="field">
                        <label>Payment Source</label>
                        <input type="text" name="Payment_Source" value="{{ old('Payment_Source', $payment?->Payment_Source) }}">
                    </div>
                    <div class="field">
                        <label>Transaction ID</label>
                        <input type="text" name="Transaction_ID" value="{{ old('Transaction_ID', $payment?->Transaction_ID) }}">
                    </div>
                    <div class="field">
                        <label>Customer User ID</label>
                        <input type="number" min="1" name="user_id" id="customer_user_id" value="{{ old('user_id', $linkedCredit?->user_id ?? $customer?->user_id) }}">
                    </div>
                    <div class="field" style="max-width:none;">
                        <label>Selected Customer</label>
                        <div id="selected_customer_summary" class="muted" style="padding:12px 14px;border:1px solid rgba(32,64,96,0.12);border-radius:14px;background:#f7fafc;">
                            @if ($customer)
                                {{ trim(implode(' | ', array_filter([
                                    $customer->user_id,
                                    $customer->display_name,
                                    $customer->user_name ? '@'.$customer->user_name : null,
                                    $customer->user_email,
                                    'Current unpaid total '.number_format((float) $dueTotal, 2),
                                ]))) }}
                            @else
                                Search and select a customer to fill the user ID safely.
                            @endif
                        </div>
                    </div>
                    <div class="field" style="max-width:none;">
                        <label>Notes</label>
                        <input type="text" name="notes" value="{{ old('notes', $linkedCredit?->notes) }}">
                    </div>
                    @unless ($payment)
                        <div class="field" style="max-width:none;">
                            <label style="display:flex;align-items:center;gap:10px;color:inherit;font-weight:700;">
                                <input type="checkbox" name="apply_to_due" value="1" {{ old('apply_to_due', '1') ? 'checked' : '' }}>
                                Apply this payment to the customer's unpaid invoices first
                            </label>
                            <span class="muted">If the payment is greater than the unpaid total, the extra amount is kept as customer balance.</span>
                        </div>
                    @endunless
                    <div class="field" style="min-width:auto;">
                        <label>&nbsp;</label>
                        <button type="submit">Save Payment</button>
                    </div>
                </form>
            </div>
        </section>
    @endunless

    <script>
        (() => {
            const lookupInput = document.getElementById('customer_lookup');
            const userIdInput = document.getElementById('customer_user_id');
            const resultsWrap = document.getElementById('customer_lookup_results');
            const resultsInner = document.getElementById('customer_lookup_results_inner');
            const summary = document.getElementById('selected_customer_summary');

            if (!lookupInput || !userIdInput || !resultsWrap || !resultsInner || !summary) {
                return;
            }

            let activeRequest = 0;

            const hideResults = () => {
                resultsWrap.style.display = 'none';
                resultsInner.innerHTML = '';
            };

            const renderSummary = (customer) => {
                if (!customer) {
                    summary.textContent = 'Search and select a customer to fill the user ID safely.';
                    return;
                }

                const parts = [
                    `${customer.user_id}`,
                    customer.display_name,
                    customer.user_name ? `@${customer.user_name}` : '',
                    customer.user_email || '',
                    `Current unpaid total ${Number(customer.due_total || 0).toFixed(2)}`,
                ].filter(Boolean);

                summary.textContent = parts.join(' | ');
            };

            const escapeHtml = (value) => {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            };

            const pickCustomer = (customer) => {
                userIdInput.value = customer.user_id;
                lookupInput.value = customer.summary;
                renderSummary(customer);
                hideResults();
            };

            const searchCustomers = async (term) => {
                const requestId = ++activeRequest;
                const response = await fetch(`{{ url('/v/customer-lookup.php') }}?q=${encodeURIComponent(term)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (requestId !== activeRequest) {
                    return;
                }

                if (!response.ok) {
                    hideResults();
                    return;
                }

                const payload = await response.json();
                const customers = Array.isArray(payload.customers) ? payload.customers : [];

                if (customers.length === 0) {
                    resultsInner.innerHTML = '<div class="muted" style="padding:10px 12px;">No matching active customers found.</div>';
                    resultsWrap.style.display = 'block';
                    return;
                }

                resultsInner.innerHTML = customers.map((customer) => `
                    <button
                        type="button"
                        class="badge"
                        data-user-id="${escapeHtml(customer.user_id)}"
                        data-display-name="${escapeHtml(customer.display_name)}"
                        data-user-name="${escapeHtml(customer.user_name || '')}"
                        data-user-email="${escapeHtml(customer.user_email || '')}"
                        data-due-total="${escapeHtml(Number(customer.due_total || 0).toFixed(2))}"
                        data-summary="${escapeHtml(customer.summary)}"
                        style="display:block;width:100%;text-align:left;border:none;background:#fff;margin:0 0 6px;padding:12px 14px;border-radius:12px;"
                    >
                        <strong>${escapeHtml(customer.user_id)}</strong> | ${escapeHtml(customer.display_name)}<br>
                        <span class="muted">${customer.user_name ? '@' + escapeHtml(customer.user_name) + ' | ' : ''}${escapeHtml(customer.user_email || '')} | Due ${escapeHtml(Number(customer.due_total || 0).toFixed(2))}</span>
                    </button>
                `).join('');

                resultsWrap.style.display = 'block';

                resultsInner.querySelectorAll('[data-user-id]').forEach((button) => {
                    button.addEventListener('click', () => {
                        pickCustomer({
                            user_id: button.dataset.userId,
                            display_name: button.dataset.displayName,
                            user_name: button.dataset.userName,
                            user_email: button.dataset.userEmail,
                            due_total: button.dataset.dueTotal,
                            summary: button.dataset.summary,
                        });
                    });
                });
            };

            lookupInput.addEventListener('input', () => {
                const term = lookupInput.value.trim();

                if ((/^\d+$/.test(term) && term.length >= 1) || term.length >= 2) {
                    searchCustomers(term).catch(() => hideResults());
                } else {
                    hideResults();
                }
            });

            lookupInput.addEventListener('blur', () => {
                window.setTimeout(hideResults, 150);
            });

            userIdInput.addEventListener('input', () => {
                if (userIdInput.value.trim() === '') {
                    renderSummary(null);
                }
            });
        })();
    </script>
@endsection
