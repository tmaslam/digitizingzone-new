@extends('layouts.admin')

@section('title', 'Create Order / Quote | 1Dollar Admin')
@section('page_heading', 'Create Order / Quote')
@section('page_subheading', 'Create an assisted admin entry without changing the customer-facing flow.')

@section('content')
    @php
        $fabricTypeOptions = [
            'Pique Polo',
            'Jersey',
            'Fleece',
            'Twil',
            'Towel',
            'Canvas',
            'Leather',
            'Hat/visor',
            'Beanie',
            'Other',
        ];
        $selectedAppliques = old('appliques', 'no');
        $selectedWorkType = old('work_type', 'digitizing');
        $selectedFormat = old('format');
    @endphp

    <style>
        .admin-entry-form {
            display: grid;
            gap: 18px;
        }

        .admin-entry-form .entry-section {
            padding: 20px;
            border: 1px solid rgba(24, 34, 45, 0.12);
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(247, 243, 234, 0.84));
            box-shadow: 0 14px 34px rgba(20, 33, 49, 0.08);
        }

        .admin-entry-form .entry-section h3 {
            margin: 0 0 12px;
            font-size: 1.05rem;
        }

        .admin-entry-form .toolbar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: start;
        }

        .admin-entry-form .field,
        .admin-entry-form .paired-field,
        .admin-entry-form .dimension-field {
            min-width: 0;
            max-width: none;
            width: 100%;
            flex: none;
        }

        .admin-entry-form .field-full {
            min-width: 100%;
            width: 100%;
            max-width: none;
        }

        .admin-entry-form .field-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .paired-field {
            display: grid;
            gap: 8px;
            min-width: 220px;
            flex: 1 1 320px;
            max-width: 420px;
        }

        .paired-field > span {
            font-size: 0.84rem;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.3;
        }

        .paired-field-inputs {
            display: grid;
            grid-template-columns: minmax(110px, 0.85fr) minmax(0, 1.15fr);
            gap: 10px;
            align-items: start;
        }

        .dimension-field {
            display: grid;
            gap: 8px;
            min-width: 220px;
            flex: 1 1 260px;
            max-width: 320px;
        }

        .dimension-field > span {
            font-size: 0.84rem;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.3;
        }

        .dimension-inputs {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            gap: 10px;
            align-items: center;
        }

        .dimension-divider {
            color: #64748b;
            font-weight: 700;
            font-size: 1rem;
        }

        .appliques-choice {
            display: flex;
            gap: 18px;
            align-items: center;
            flex-wrap: wrap;
            min-height: 42px;
        }

        .appliques-choice label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #1f2937;
        }

        .helper-text {
            display: block;
            color: #64748b;
            font-size: 0.86rem;
            margin-top: 6px;
        }

        [hidden] {
            display: none !important;
        }

    </style>

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-body">
            @unless ($hasWorkflowMetaTable)
                <div class="alert" style="margin-bottom:18px;">
                    This feature requires the `order_workflow_meta` table. Run the SQL file in `database/sql/order_workflow_meta.sql` first.
                </div>
            @endunless

            <form method="post" action="{{ url('/v/create-order.php') }}" enctype="multipart/form-data" class="admin-entry-form">
                @csrf
                <input type="hidden" name="sew_out" value="{{ old('sew_out', 'no') }}">

                <section class="entry-section">
                        <h3>Order Setup</h3>
                        <div class="toolbar">
                            <div class="field">
                                <label for="entry_stage">Record Stage</label>
                                <select id="entry_stage" name="entry_stage" @disabled(! $hasWorkflowMetaTable)>
                                    <option value="new" @selected(old('entry_stage', 'new') === 'new')>New Order</option>
                                    <option value="completed_unpaid" @selected(old('entry_stage') === 'completed_unpaid')>Unpaid Order</option>
                                    <option value="completed_paid" @selected(old('entry_stage') === 'completed_paid')>Paid Order</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="flow_context">Queue</label>
                                <select id="flow_context" name="flow_context" @disabled(! $hasWorkflowMetaTable)>
                                    <option value="order" @selected(old('flow_context', 'order') === 'order')>Order Queue</option>
                                    <option value="code" @selected(old('flow_context') === 'code')>Quote Queue</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="work_type">Work Type</label>
                                <select id="work_type" name="work_type" @disabled(! $hasWorkflowMetaTable)>
                                    <option value="digitizing" @selected(old('work_type', 'digitizing') === 'digitizing')>Digitizing</option>
                                    <option value="vector" @selected(old('work_type') === 'vector')>Vector</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="website">Website</label>
                                <select id="website" name="website" @disabled(! $hasWorkflowMetaTable)>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site['legacy_key'] }}" @selected($selectedWebsite === $site['legacy_key'])>{{ $site['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="customer_user_id">Customer ID</label>
                                <input
                                    id="customer_user_id"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    name="customer_user_id"
                                    list="customer-user-list"
                                    value="{{ old('customer_user_id', $customer?->user_id) }}"
                                    @disabled(! $hasWorkflowMetaTable)
                                >
                                <datalist id="customer-user-list"></datalist>
                                <span class="helper-text">Type a customer ID or pick from active customers on the selected website.</span>
                            </div>
                        </div>
                        @if ($customer)
                            <p class="muted" style="margin:12px 0 0;">
                                Customer found: <strong>{{ $customer->display_name }}</strong>{{ $customer->user_email ? ' | '.$customer->user_email : '' }}
                            </p>
                        @endif
                </section>

                <section class="entry-section">
                        <h3>Order Details</h3>
                        <div class="toolbar">
                            <div class="field">
                                <label for="design_name">Design / Order Name</label>
                                <input id="design_name" type="text" name="design_name" value="{{ old('design_name') }}" @disabled(! $hasWorkflowMetaTable)>
                            </div>
                            <div class="field">
                                <label for="subject">Subject</label>
                                <input id="subject" type="text" name="subject" value="{{ old('subject') }}" @disabled(! $hasWorkflowMetaTable)>
                            </div>
                            <div class="field">
                                <label for="format">Format</label>
                                <select id="format" name="format" data-digitizing-options='@json($digitizingFormatOptions)' data-vector-options='@json($vectorFormatOptions)' @disabled(! $hasWorkflowMetaTable)></select>
                            </div>
                            <div class="field" data-digitizing-only>
                                <label for="fabric_type">Fabric Type</label>
                                <select id="fabric_type" name="fabric_type" @disabled(! $hasWorkflowMetaTable)>
                                    @foreach ($fabricTypeOptions as $option)
                                        <option value="{{ $option }}" @selected(old('fabric_type', 'Pique Polo') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="turn_around_time">Turnaround</label>
                                <select id="turn_around_time" name="turn_around_time" @disabled(! $hasWorkflowMetaTable)>
                                    @foreach ($turnaroundOptions as $option)
                                        <option value="{{ $option }}" @selected(old('turn_around_time', 'Standard') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="dimension-field" data-digitizing-only>
                                <span>Design Size</span>
                                <div class="dimension-inputs">
                                    <input id="width" type="number" step="0.01" min="0" inputmode="decimal" name="width" value="{{ old('width') }}" placeholder="Width" @disabled(! $hasWorkflowMetaTable)>
                                    <span class="dimension-divider">X</span>
                                    <input id="height" type="number" step="0.01" min="0" inputmode="decimal" name="height" value="{{ old('height') }}" placeholder="Height" @disabled(! $hasWorkflowMetaTable)>
                                </div>
                            </div>
                            <div class="field" data-digitizing-only>
                                <label for="measurement">Measurement</label>
                                <select id="measurement" name="measurement" @disabled(! $hasWorkflowMetaTable)>
                                    <option value="Inches" @selected(old('measurement', 'Inches') === 'Inches')>Inches</option>
                                    <option value="CM" @selected(old('measurement') === 'CM')>CM</option>
                                    <option value="MM" @selected(old('measurement') === 'MM')>MM</option>
                                </select>
                            </div>
                            <div class="paired-field" data-digitizing-only>
                                <span>Colors</span>
                                <div class="paired-field-inputs">
                                    <input id="no_of_colors" type="number" min="0" name="no_of_colors" value="{{ old('no_of_colors', 0) }}" placeholder="No. of Colors" @disabled(! $hasWorkflowMetaTable)>
                                    <input id="color_names" type="text" name="color_names" value="{{ old('color_names') }}" placeholder="Color Names" @disabled(! $hasWorkflowMetaTable)>
                                </div>
                            </div>
                            <div class="field" data-digitizing-only>
                                <label>Appliques</label>
                                <div class="appliques-choice">
                                    <label for="appliques_yes">
                                        <input id="appliques_yes" type="radio" name="appliques" value="yes" @checked($selectedAppliques === 'yes') @disabled(! $hasWorkflowMetaTable)>
                                        <span>Yes</span>
                                    </label>
                                    <label for="appliques_no">
                                        <input id="appliques_no" type="radio" name="appliques" value="no" @checked($selectedAppliques !== 'yes') @disabled(! $hasWorkflowMetaTable)>
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>
                            <div class="paired-field" data-digitizing-only data-applique-details>
                                <span>Applique Details</span>
                                <div class="paired-field-inputs">
                                    <input id="no_of_appliques" type="number" min="0" name="no_of_appliques" value="{{ old('no_of_appliques', 0) }}" placeholder="No. of Appliques" data-applique-input @disabled(! $hasWorkflowMetaTable)>
                                    <input id="applique_colors" type="text" name="applique_colors" value="{{ old('applique_colors') }}" placeholder="Applique Colors" data-applique-input @disabled(! $hasWorkflowMetaTable)>
                                </div>
                            </div>
                        </div>

                        <div class="field field-full" style="margin-top:14px;">
                            <label for="customer_notes">Customer Notes</label>
                            <textarea id="customer_notes" name="customer_notes" rows="5" @disabled(! $hasWorkflowMetaTable)>{{ old('customer_notes') }}</textarea>
                        </div>

                        <div class="field field-full" style="margin-top:14px;">
                            <label for="additional_details">Additional Details</label>
                            <textarea id="additional_details" name="additional_details" rows="4" @disabled(! $hasWorkflowMetaTable)>{{ old('additional_details') }}</textarea>
                        </div>

                        <div class="field field-full" style="margin-top:14px;">
                            <label for="admin_note">Admin Internal Note</label>
                            <textarea id="admin_note" name="admin_note" rows="4" @disabled(! $hasWorkflowMetaTable)>{{ old('admin_note') }}</textarea>
                        </div>
                </section>

                <section class="entry-section">
                        <h3>Delivery And Credit Control</h3>
                        <div class="toolbar">
                            <div class="field">
                                <label for="order_credit_limit">Per-Order Credit Limit</label>
                                <input id="order_credit_limit" type="number" step="0.01" min="0" name="order_credit_limit" value="{{ old('order_credit_limit') }}" @disabled(! $hasWorkflowMetaTable)>
                            </div>
                            <div class="field">
                                <label for="delivery_override">Customer File Access</label>
                                <select id="delivery_override" name="delivery_override" @disabled(! $hasWorkflowMetaTable)>
                                    <option value="auto" @selected(old('delivery_override', 'auto') === 'auto')>Follow Payment / Credit Rules</option>
                                    <option value="preview_only" @selected(old('delivery_override') === 'preview_only')>Preview Files Only</option>
                                </select>
                            </div>
                        </div>
                        <p class="muted" style="margin:12px 0 0;">
                            This only affects customer delivery. Internal admin review and assignment still see the full record.
                        </p>
                        <p class="muted" style="margin:8px 0 0;">
                            Completed paid records can be released immediately after save. Completed unpaid records still follow payment and credit rules.
                        </p>
                </section>

                <section class="entry-section" data-completed-paid-panel>
                        <h3>Completed Record Details</h3>
                        <p class="muted" style="margin:0 0 14px;">
                            Use this only when admin is entering a record that is already completed outside the normal workflow.
                        </p>
                        <div class="toolbar">
                            <div class="field">
                                <label for="stitches">Stitches / Hours</label>
                                <input id="stitches" type="text" name="stitches" value="{{ old('stitches') }}" @disabled(! $hasWorkflowMetaTable)>
                                <span class="muted">Use stitches for digitizing, or hours like `3` or `3:30` for vector work.</span>
                            </div>
                            <div class="field">
                                <label for="amount">Final Amount</label>
                                <input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" @disabled(! $hasWorkflowMetaTable)>
                                <span class="muted">Leave blank to calculate from the site pricing profile, with customer overrides only when that customer has special pricing.</span>
                            </div>
                            <div class="field">
                                <label for="submitted_at">Submitted Date</label>
                                <input id="submitted_at" type="datetime-local" name="submitted_at" value="{{ old('submitted_at') }}" @disabled(! $hasWorkflowMetaTable)>
                            </div>
                            <div class="field">
                                <label for="completed_at">Completed / Delivered Date</label>
                                <input id="completed_at" type="datetime-local" name="completed_at" value="{{ old('completed_at') }}" @disabled(! $hasWorkflowMetaTable)>
                            </div>
                            <div class="field">
                                <label for="completed_files">Completed Customer Files</label>
                                <input id="completed_files" type="file" name="completed_files[]" multiple accept="{{ \App\Support\UploadSecurity::acceptAttribute('production') }}" @disabled(! $hasWorkflowMetaTable)>
                                <span class="muted" data-completed-file-help>Upload finished files here. They appear in admin review first, and admin can choose which files to release to the customer.</span>
                            </div>
                        </div>
                </section>

                <section class="entry-section">
                        <h3>Files</h3>
                        <div class="toolbar">
                            <div class="field field-full" style="min-width:280px;flex:1 1 420px;">
                                <label for="source_files" data-source-file-label>Customer Source / Intake Files</label>
                                <input id="source_files" type="file" name="source_files[]" multiple accept="{{ $sourceFileAccept }}" @disabled(! $hasWorkflowMetaTable)>
                                <span class="muted" data-source-file-help>Upload customer-provided source or intake files here so admin can finish the entry without jumping back through the form.</span>
                            </div>
                        </div>
                </section>

                <p class="muted" style="margin:16px 0 0;">
                    Saving from this screen does not send customer, team, or supervisor notification emails.
                </p>

                <div class="field-actions">
                    <button type="submit" @disabled(! $hasWorkflowMetaTable)>Create Entry</button>
                    <a class="badge" href="{{ \App\Support\AdminOrderQueues::url('new-orders') }}">Back to Orders</a>
                </div>
            </form>
        </div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const stageField = document.getElementById('entry_stage');
        const completedPaidPanel = document.querySelector('[data-completed-paid-panel]');
        const stitchesField = document.getElementById('stitches');
        const amountField = document.getElementById('amount');
        const customerField = document.getElementById('customer_user_id');
        const customerList = document.getElementById('customer-user-list');
        const websiteField = document.getElementById('website');
        const flowField = document.getElementById('flow_context');
        const workTypeField = document.getElementById('work_type');
        const formatField = document.getElementById('format');
        const turnaroundField = document.getElementById('turn_around_time');
        const tokenField = document.querySelector('input[name="_token"]');
        const digitizingOnlyFields = Array.from(document.querySelectorAll('[data-digitizing-only]'));
        const sourceFileLabel = document.querySelector('[data-source-file-label]');
        const sourceFileHelp = document.querySelector('[data-source-file-help]');
        const completedFileHelp = document.querySelector('[data-completed-file-help]');
        const customerOptions = @json($customerOptions);
        const appliqueRadios = Array.from(document.querySelectorAll('input[name="appliques"]'));
        const appliqueDetails = document.querySelector('[data-applique-details]');
        const appliqueInputs = Array.from(document.querySelectorAll('[data-applique-input]'));
        const selectedWorkType = @json($selectedWorkType);
        const selectedFormat = @json($selectedFormat);

        if (!stageField || !completedPaidPanel) {
            return;
        }

        const syncCustomerOptions = function () {
            if (!customerList || !websiteField) {
                return;
            }

            customerList.innerHTML = '';

            customerOptions
                .filter((customer) => customer.website === websiteField.value)
                .forEach((customer) => {
                    const option = document.createElement('option');
                    option.value = String(customer.user_id);
                    option.label = customer.email !== ''
                        ? `${customer.display_name} - ${customer.email}`
                        : customer.display_name;
                    customerList.appendChild(option);
                });
        };

        const syncWorkType = function () {
            if (!workTypeField) {
                return;
            }

            const isDigitizing = workTypeField.value === 'digitizing';
            digitizingOnlyFields.forEach((field) => {
                field.hidden = !isDigitizing;
            });
            syncAppliqueDetails();
        };

        const syncFormatOptions = function () {
            if (!formatField || !workTypeField) {
                return;
            }

            const options = workTypeField.value === 'vector'
                ? JSON.parse(formatField.dataset.vectorOptions || '[]')
                : JSON.parse(formatField.dataset.digitizingOptions || '[]');
            const currentValue = formatField.value || selectedFormat || '';
            const values = currentValue !== '' && !options.includes(currentValue)
                ? options.concat([currentValue])
                : options;

            formatField.innerHTML = '<option value="">Please Select</option>';

            values.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = String(value).toUpperCase();
                option.selected = currentValue === value;
                formatField.appendChild(option);
            });
        };

        const syncAppliqueDetails = function () {
            if (!appliqueDetails || !appliqueRadios.length) {
                return;
            }

            const isDigitizing = !workTypeField || workTypeField.value === 'digitizing';
            const selected = appliqueRadios.find((radio) => radio.checked);
            const enabled = isDigitizing && selected && selected.value === 'yes';

            appliqueDetails.hidden = !enabled;
            appliqueInputs.forEach((input) => {
                input.disabled = !enabled || !{{ $hasWorkflowMetaTable ? 'true' : 'false' }};
                if (!enabled) {
                    input.value = '';
                }
            });
        };

        const syncStage = function () {
            const stage = stageField.value;
            const isCompletedStage = stage === 'completed_paid' || stage === 'completed_unpaid';

            completedPaidPanel.style.display = isCompletedStage ? '' : 'none';

            if (sourceFileLabel) {
                sourceFileLabel.textContent = isCompletedStage ? 'Customer Source / Intake Files' : 'Customer Source Files';
            }

            if (sourceFileHelp) {
                sourceFileHelp.textContent = isCompletedStage
                    ? 'Optional intake files for record keeping. Use the completed files field below for finished customer-facing output.'
                    : 'Upload customer-provided source files for admin review and assignment.';
            }

            if (completedFileHelp) {
                completedFileHelp.textContent = stage === 'completed_paid'
                    ? 'Upload finished files here. They will appear in admin review first, and because this record is already paid admin can release them immediately from order detail.'
                    : 'Upload finished files here. They stay in admin review until admin releases them to the customer.';
            }
        };

        const requestAmountPreview = async function () {
            if (!stitchesField || !amountField || !customerField || !flowField || !workTypeField || !turnaroundField || !tokenField) {
                return;
            }

            const stitches = stitchesField.value.trim();
            const customerId = customerField.value.trim();

            if ((stageField.value !== 'completed_paid' && stageField.value !== 'completed_unpaid') || stitches === '' || customerId === '') {
                return;
            }

            try {
                const response = await fetch("{{ url('/v/create-order/price-preview') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tokenField.value,
                    },
                    body: JSON.stringify({
                        flow_context: flowField.value,
                        work_type: workTypeField.value,
                        website: websiteField ? websiteField.value : '',
                        customer_user_id: customerId,
                        turn_around_time: turnaroundField.value,
                        stitches: stitches,
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    return;
                }

                stitchesField.value = data.stitches ?? stitchesField.value;
                amountField.value = data.amount ?? amountField.value;
            } catch (error) {
                // Keep the form usable even if preview is unavailable.
            }
        };

        syncStage();
        if (workTypeField) {
            workTypeField.value = selectedWorkType;
        }
        syncFormatOptions();
        syncWorkType();
        syncCustomerOptions();
        syncAppliqueDetails();
        stageField.addEventListener('change', syncStage);
        if (workTypeField) {
            workTypeField.addEventListener('change', function () {
                syncFormatOptions();
                syncWorkType();
            });
        }
        appliqueRadios.forEach((radio) => {
            radio.addEventListener('change', syncAppliqueDetails);
        });

        if (websiteField) {
            websiteField.addEventListener('change', function () {
                customerField.value = '';
                syncCustomerOptions();
            });
        }

        if (stitchesField) {
            stitchesField.addEventListener('blur', requestAmountPreview);
        }
    });
    </script>
@endsection
