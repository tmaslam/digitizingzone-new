<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Support\CustomerUploadPolicy;
use App\Support\OrderAutomation;
use App\Support\OrderWorkflowMetaManager;
use App\Support\PortalMailer;
use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use App\Support\SitePricing;
use App\Support\SiteContext;
use App\Support\SystemEmailTemplates;
use App\Support\UploadSecurity;
use App\Support\UploadFileName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CustomerOrderEntryController extends Controller
{
    private const CUSTOMER_SOURCE_FILE_MAX_KB = 25600;

    private const FABRIC_TYPE_OPTIONS = [
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

    private const MEASUREMENT_OPTIONS = ['Inches', 'CM', 'MM'];

    private const DIGITIZING_FORMAT_OPTIONS = ['DST', 'PES', 'EXP', 'EMB', 'PXF', 'NGS', 'other'];

    private const VECTOR_FORMAT_OPTIONS = ['AI', 'EPS', 'PSD', 'PDF', 'SVG', 'other'];

    private const STARTING_POINTS = [
        'TopLeft', 'TopCenter', 'TopRight',
        'MiddleLeft', 'Center', 'MiddleRight',
        'BottomLeft', 'BottomCenter', 'BottomRight',
    ];

    private const TURNAROUND_OPTIONS = ['Standard', 'Priority', 'Superrush'];

    public function create(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $flow = $this->flowConfig($request);
        OrderAutomation::syncCustomer($customer, $site);
        $placement = $this->placementState($customer, $site);

        return view('customer.orders.form', [
            'pageTitle' => $flow['page_title'],
            'customer' => $customer,
            'site' => $site,
            'flow' => $flow,
            'order' => null,
            'mode' => 'create',
            'formAction' => $flow['submit_path'],
            'submitLabel' => $flow['submit_label'],
            'placement' => $placement,
            'fabricTypeOptions' => self::FABRIC_TYPE_OPTIONS,
            'turnaroundOptions' => self::TURNAROUND_OPTIONS,
            'turnaroundOptionLabels' => $this->turnaroundOptionLabels($customer, $site, $flow['work_type']),
            'measurementOptions' => self::MEASUREMENT_OPTIONS,
            'preferredFormat' => $this->preferredFormatForFlow($customer, $flow['work_type']),
            'formatOptions' => $this->formatOptionsForFlow($flow['work_type'], $this->preferredFormatForFlow($customer, $flow['work_type'])),
            'sourceFileAccept' => CustomerUploadPolicy::customerSourceAcceptAttribute(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        $flow = $this->flowConfig($request);
        OrderAutomation::syncCustomer($customer, $site);
        $placement = $this->placementState($customer, $site);

        if (! $placement['can_place']) {
            return back()->withInput();
        }

        $validated = $this->validateOrderInput($request, $flow, false);
        $uploadValidationError = UploadSecurity::assertAllowedFiles($request->file('source_files', []), 'source');
        if ($uploadValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, 'source', 'Customer upload was rejected during order entry.', [
                'workflow' => $flow['work_type'],
                'file_names' => collect($request->file('source_files', []))
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return back()->withErrors(['source_files' => $uploadValidationError])->withInput();
        }

        $submitDate = now();
        $completionDate = $this->completionDate((string) $validated['turn_around_time'], $submitDate);
        $orderType = $flow['order_type'];
        $type = $flow['work_type'] === 'vector' ? 'vector' : 'digitizing';
        [$appliques, $noOfAppliques, $appliqueColors] = $this->normalizedAppliqueValues($validated);

        $order = Order::query()->create([
            'user_id' => $customer->user_id,
            'order_num' => (string) $this->nextOrderNumber($customer, $site),
            'design_name' => $validated['design_name'],
            'format' => $validated['format'] ?? '',
            'fabric_type' => $validated['fabric_type'] ?? '',
            'sew_out' => $validated['sew_out'] ?? '',
            'width' => $request->filled('width') ? (string) $validated['width'] : '',
            'height' => $request->filled('height') ? (string) $validated['height'] : '',
            'measurement' => $validated['measurement'] ?? '',
            'no_of_colors' => (int) ($validated['no_of_colors'] ?? 0),
            'color_names' => $validated['color_names'] ?? '',
            'appliques' => $appliques,
            'no_of_appliques' => $noOfAppliques,
            'applique_colors' => $appliqueColors,
            'starting_point' => trim((string) ($validated['starting_point'] ?? '')),
            'comments1' => $validated['comments'] ?? '',
            'comments2' => '',
            'status' => 'Underprocess',
            'stitches_price' => '0',
            'total_amount' => '0',
            'turn_around_time' => $validated['turn_around_time'],
            'submit_date' => $submitDate->format('Y-m-d H:i:s'),
            'completion_date' => $completionDate,
            'modified_date' => $submitDate->format('Y-m-d H:i:s'),
            'stitches' => '0',
            'is_active' => 1,
            'order_type' => $orderType,
            'order_status' => '',
            'website' => $site->legacyKey,
            'notes_by_user' => trim((string) ($validated['comments'] ?? '')) !== '' ? 1 : 0,
            'notes_by_admin' => 0,
            'advance_pay' => '0',
            'subject' => $validated['design_name'],
            'sent' => 'Normal',
            'working' => '',
            'del_attachment' => 0,
            'type' => $type,
        ]);

        $this->rememberPreferredFormat($customer, $flow['work_type'], $validated['format'] ?? '');

        if (trim((string) ($validated['comments'] ?? '')) !== '') {
            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => trim((string) $validated['comments']),
                'source_page' => 'customerComments',
                'comment_source' => 'customerComments',
                'date_added' => $submitDate->format('Y-m-d H:i:s'),
                'date_modified' => $submitDate->format('Y-m-d H:i:s'),
            ]);
        }

        $this->storeSourceFiles($request->file('source_files', []), $order, $flow['source_file_source']);
        OrderWorkflowMetaManager::ensure($order, [
            'created_source' => 'customer',
            'delivery_override' => 'auto',
        ]);
        $this->sendCustomerSubmissionConfirmation($customer, $site, $order, $flow);
        $this->sendAdminAlertForSubmission($customer, $site, $order, $flow);

        OrderAutomation::syncCustomer($customer, $site, true);

        return redirect($flow['success_redirect'])->with('success', $flow['success_message']);
    }

    public function edit(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $order = $this->editableOrder($customer, $site, (int) $request->query('order_id', 0));
        abort_unless($order, 404);

        $flow = $this->flowForExistingOrder($order);

        return view('customer.orders.form', [
            'pageTitle' => 'Edit '.$flow['page_title'],
            'customer' => $customer,
            'site' => $site,
            'flow' => $flow,
            'order' => $order,
            'mode' => 'edit',
            'formAction' => $this->editFormAction($order),
            'submitLabel' => 'Save Changes',
            'placement' => $this->placementState($customer, $site),
            'fabricTypeOptions' => self::FABRIC_TYPE_OPTIONS,
            'turnaroundOptions' => self::TURNAROUND_OPTIONS,
            'turnaroundOptionLabels' => $this->turnaroundOptionLabels($customer, $site, $flow['work_type']),
            'measurementOptions' => self::MEASUREMENT_OPTIONS,
            'preferredFormat' => '',
            'formatOptions' => $this->formatOptionsForFlow($flow['work_type'], (string) old('format', $order?->format)),
            'sourceFileAccept' => CustomerUploadPolicy::customerSourceAcceptAttribute(),
            'existingAttachments' => Attachment::query()
                ->where('order_id', $order->order_id)
                ->whereIn('file_source', ['order', 'vector', 'quote', 'color', 'edit order'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $order = $this->editableOrder($customer, $site, (int) $request->query('order_id', 0));
        abort_unless($order, 404);

        $flow = $this->flowForExistingOrder($order);
        $validated = $this->validateOrderInput($request, $flow, true);
        $uploadValidationError = UploadSecurity::assertAllowedFiles($request->file('source_files', []), 'source');
        if ($uploadValidationError !== null) {
            return back()->withErrors(['source_files' => $uploadValidationError])->withInput();
        }
        [$appliques, $noOfAppliques, $appliqueColors] = $this->normalizedAppliqueValues($validated);

        $order->update([
            'design_name' => $validated['design_name'],
            'format' => $validated['format'] ?? '',
            'fabric_type' => $validated['fabric_type'] ?? '',
            'sew_out' => $validated['sew_out'] ?? '',
            'width' => $request->filled('width') ? (string) $validated['width'] : '',
            'height' => $request->filled('height') ? (string) $validated['height'] : '',
            'measurement' => $validated['measurement'] ?? '',
            'no_of_colors' => (int) ($validated['no_of_colors'] ?? 0),
            'color_names' => $validated['color_names'] ?? '',
            'appliques' => $appliques,
            'no_of_appliques' => $noOfAppliques,
            'applique_colors' => $appliqueColors,
            'starting_point' => trim((string) ($validated['starting_point'] ?? '')),
            'comments1' => $validated['comments'] ?? '',
            'subject' => $validated['design_name'],
            'status' => 'Underprocess',
            'modified_date' => now()->format('Y-m-d H:i:s'),
            'turn_around_time' => $validated['turn_around_time'],
            'completion_date' => $this->completionDate((string) $validated['turn_around_time'], now()),
        ]);

        $this->rememberPreferredFormat($customer, $flow['work_type'], $validated['format'] ?? '');

        if (trim((string) ($validated['comments'] ?? '')) !== '') {
            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => trim((string) $validated['comments']),
                'source_page' => 'customerComments',
                'comment_source' => 'customerComments',
                'date_added' => now()->format('Y-m-d H:i:s'),
                'date_modified' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        $this->storeSourceFiles($request->file('source_files', []), $order, 'edit order');
        $this->sendAdminAlertForAction($customer, $site, $order, 'Customer Order Updated', trim((string) ($validated['comments'] ?? '')));

        return redirect($this->isQuoteType($order) ? url('/view-quotes.php') : url('/view-orders.php'))
            ->with('success', 'Your order has been updated successfully.');
    }

    public function revision(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $order = $this->revisableOrder($customer, $site, (int) $request->query('order_id', 0));
        abort_unless($order, 404);

        return view('customer.orders.revision', [
            'pageTitle' => 'Request an Edit',
            'order' => $order,
            'sourceFileAccept' => CustomerUploadPolicy::customerSourceAcceptAttribute(),
        ]);
    }

    public function submitRevision(Request $request)
    {
        $customer = $this->customer($request);
        $site = $this->site($request);
        OrderAutomation::syncCustomer($customer, $site);
        $order = $this->revisableOrder($customer, $site, (int) $request->query('order_id', 0));
        abort_unless($order, 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'comments' => ['required', 'string', 'max:5000'],
            'source_files.*' => ['nullable', 'file', 'max:'.self::CUSTOMER_SOURCE_FILE_MAX_KB],
        ], [
            'source_files.*.max' => 'Each uploaded file must be 25 MB or smaller.',
        ]);

        $uploadValidationError = UploadSecurity::assertAllowedFiles($request->file('source_files', []), 'source');
        if ($uploadValidationError !== null) {
            return back()->withErrors(['source_files' => $uploadValidationError])->withInput();
        }

        $now = now()->format('Y-m-d H:i:s');

        $order->update([
            'subject' => trim((string) $validated['subject']),
            'comments2' => trim((string) $validated['comments']),
            'status' => 'disapproved',
            'modified_date' => $now,
            'stitches_price' => '0',
            'stitches' => '0',
            'total_amount' => '0',
            'advance_pay' => '0',
        ]);

        OrderComment::query()->create([
            'order_id' => $order->order_id,
            'comments' => trim((string) $validated['comments']),
            'source_page' => 'customerComments',
            'comment_source' => 'customerComments',
            'date_added' => $now,
            'date_modified' => $now,
        ]);

        $this->storeSourceFiles($request->file('source_files', []), $order, 'edit order');
        $this->sendAdminAlertForAction($customer, $site, $order, 'Customer Revision Requested', trim((string) $validated['comments']));

        return redirect(url('/view-orders.php'))->with('success', 'Your edit request has been sent successfully.');
    }

    private function validateOrderInput(Request $request, array $flow, bool $editing): array
    {
        $commentsRule = ['nullable', 'string', 'max:5000'];
        $sourceFilesRule = $editing ? ['nullable', 'array'] : ['required', 'array', 'min:1'];

        return $request->validate([
            'design_name' => ['required', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:255'],
            'fabric_type' => ['nullable', 'in:'.implode(',', self::FABRIC_TYPE_OPTIONS)],
            'sew_out' => ['nullable', 'in:yes,no'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'measurement' => ['nullable', 'in:'.implode(',', self::MEASUREMENT_OPTIONS)],
            'no_of_colors' => ['nullable', 'integer', 'min:0'],
            'color_names' => ['nullable', 'string', 'max:1000'],
            'appliques' => ['nullable', 'in:yes,no'],
            'no_of_appliques' => ['nullable', 'integer', 'min:0'],
            'applique_colors' => ['nullable', 'string', 'max:255'],
            'starting_point' => ['nullable', 'in:'.implode(',', self::STARTING_POINTS)],
            'turn_around_time' => ['required', 'in:'.implode(',', self::TURNAROUND_OPTIONS)],
            'comments' => $commentsRule,
            'source_files' => $sourceFilesRule,
            'source_files.*' => ['nullable', 'file', 'max:'.self::CUSTOMER_SOURCE_FILE_MAX_KB],
        ], [
            'source_files.required' => 'Please upload at least one file.',
            'source_files.min' => 'Please upload at least one file.',
            'source_files.*.max' => 'Each uploaded file must be 25 MB or smaller.',
        ]);
    }

    private function flowConfig(Request $request): array
    {
        $pid = strtolower(trim((string) $request->query('pid', '')));
        $path = strtolower(trim((string) $request->path()));

        return match (true) {
            $path === 'vector_quote.php' || $path === 'vector-quote.php' || $pid === 'qv' => [
                'page_title' => 'New Vector Quote',
                'flow_context' => 'code',
                'work_type' => 'vector',
                'order_type' => 'q-vector',
                'source_file_source' => 'quote',
                'submit_path' => url('/vector_quote.php'),
                'success_redirect' => url('/view-quotes.php'),
                'success_message' => 'Your vector quote has been submitted successfully.',
                'submit_label' => 'Submit Quote',
            ],
            $path === 'quote.php' || $path === 'digitizing_quote.php' || $path === 'digitizing-quote.php' || $pid === 'qd' => [
                'page_title' => 'New Digitizing Quote',
                'flow_context' => 'code',
                'work_type' => 'digitizing',
                'order_type' => 'digitzing',
                'source_file_source' => 'quote',
                'submit_path' => url('/quote.php'),
                'success_redirect' => url('/view-quotes.php'),
                'success_message' => 'Your quote has been submitted successfully.',
                'submit_label' => 'Submit Quote',
            ],
            $path === 'vector-order.php' || $pid === 'ov' => [
                'page_title' => 'New Vector Order',
                'flow_context' => 'order',
                'work_type' => 'vector',
                'order_type' => 'vector',
                'source_file_source' => 'vector',
                'submit_path' => url('/vector-order.php'),
                'success_redirect' => url('/view-orders.php'),
                'success_message' => 'Your vector order has been submitted successfully.',
                'submit_label' => 'Submit Order',
            ],
            default => [
                'page_title' => 'New Digitizing Order',
                'flow_context' => 'order',
                'work_type' => 'digitizing',
                'order_type' => 'order',
                'source_file_source' => 'order',
                'submit_path' => url('/new-order.php'),
                'success_redirect' => url('/view-orders.php'),
                'success_message' => 'Your order has been submitted successfully.',
                'submit_label' => 'Submit Order',
            ],
        };
    }

    private function flowForExistingOrder(Order $order): array
    {
        return match ((string) $order->order_type) {
            'q-vector' => [
                'page_title' => 'Vector Quote',
                'work_type' => 'vector',
            ],
            'vector' => [
                'page_title' => 'Vector Order',
                'work_type' => 'vector',
            ],
            'digitzing', 'quote' => [
                'page_title' => 'Digitizing Quote',
                'work_type' => 'digitizing',
            ],
            default => [
                'page_title' => 'Digitizing Order',
                'work_type' => 'digitizing',
            ],
        };
    }

    private function editFormAction(Order $order): string
    {
        $path = $this->isQuoteType($order) ? url('/edit-quote.php') : url('/edit-order.php');

        return $path.'?order_id='.$order->order_id;
    }

    private function isQuoteType(Order $order): bool
    {
        return in_array((string) $order->order_type, ['digitzing', 'q-vector'], true);
    }

    private function formatOptionsForFlow(string $workType, ?string $currentValue = null): array
    {
        $options = $workType === 'vector'
            ? self::VECTOR_FORMAT_OPTIONS
            : self::DIGITIZING_FORMAT_OPTIONS;

        $currentValue = trim((string) $currentValue);
        if ($currentValue !== '' && ! in_array($currentValue, $options, true)) {
            $options[] = $currentValue;
        }

        return $options;
    }

    private function preferredFormatForFlow(AdminUser $customer, string $workType): string
    {
        $column = $workType === 'vector' ? 'vertor_format' : 'digitzing_format';

        if (! Schema::hasColumn('users', $column)) {
            return '';
        }

        return trim((string) ($customer->{$column} ?? ''));
    }

    private function turnaroundOptionLabels(AdminUser $customer, SiteContext $site, string $workType): array
    {
        $schedule = SitePricing::turnaroundFeeSchedule($customer, $site, $workType);
        $labels = [];

        foreach (self::TURNAROUND_OPTIONS as $option) {
            $code = SitePricing::normalizeTurnaround($option);
            $timingLabel = \App\Support\TurnaroundTracking::labelWithTiming($option);
            $description = $schedule[$code]['description'] ?? null;

            $labels[$option] = $description
                ? $timingLabel.' - '.$description
                : $timingLabel;
        }

        return $labels;
    }

    private function rememberPreferredFormat(AdminUser $customer, string $workType, ?string $format): void
    {
        $format = trim((string) $format);
        if ($format === '') {
            return;
        }

        $column = $workType === 'vector' ? 'vertor_format' : 'digitzing_format';

        if (! Schema::hasColumn('users', $column)) {
            return;
        }

        if (trim((string) ($customer->{$column} ?? '')) === $format) {
            return;
        }

        $customer->forceFill([$column => $format])->save();
    }

    private function placementState(AdminUser $customer, SiteContext $site): array
    {
        OrderAutomation::syncCustomer($customer, $site);
        $pendingOrders = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->whereIn('order_type', ['order', 'vector', 'color'])
            ->where('status', 'done')
            ->count();

        $pendingAmount = (float) Billing::query()
            ->active()
            ->where('user_id', $customer->user_id)
            ->where('approved', 'yes')
            ->where('payment', 'no')
            ->where(function ($query) use ($site) {
                $query->where('website', $site->legacyKey)
                    ->orWhereNull('website')
                    ->orWhere('website', '')
                    ->orWhereHas('order', function ($orderQuery) use ($site) {
                        $orderQuery->forWebsite($site->legacyKey);
                    });
            })
            ->sum(\Illuminate\Support\Facades\DB::raw('CAST(amount AS DECIMAL(12,2))'));

        $creditLimit = $this->money($customer->customer_approval_limit);
        $pendingLimit = max(0, (int) $customer->customer_pending_order_limit);

        $warning = null;
        $canPlace = true;

        if (trim((string) ($customer->user_term ?? '')) === 'upgraded') {
            $canPlace = false;
            $warning = 'Your account has been upgraded. You can no longer place new orders or quotes on the legacy portal, but you can still download your previously paid orders.';
        } elseif ($creditLimit > 0 && $pendingAmount >= $creditLimit) {
            $canPlace = false;
            $warning = "You have exceeded your credit limit of US$".number_format($creditLimit, 2).". Please clear billing or contact support to continue.";
        } elseif ($pendingLimit > 0 && $pendingOrders >= $pendingLimit) {
            $warning = "You already have {$pendingLimit} orders waiting for approval. The oldest approval-waiting order will be automatically pushed to billing when you submit new work.";
        }

        return [
            'can_place' => $canPlace,
            'pending_orders' => $pendingOrders,
            'pending_amount' => $pendingAmount,
            'warning' => $warning,
            'credit_limit' => $creditLimit,
            'pending_limit' => $pendingLimit,
        ];
    }

    private function editableOrder(AdminUser $customer, SiteContext $site, int $orderId): ?Order
    {
        return Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('order_id', $orderId)
            ->where('status', '!=', 'approved')
            ->where('status', '!=', 'done')
            ->first();
    }

    private function revisableOrder(AdminUser $customer, SiteContext $site, int $orderId): ?Order
    {
        return Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->where('order_id', $orderId)
            ->where('status', 'done')
            ->first();
    }

    private function nextOrderNumber(AdminUser $customer, SiteContext $site): int
    {
        $max = Order::query()
            ->active()
            ->forWebsite($site->legacyKey)
            ->where('user_id', $customer->user_id)
            ->get()
            ->map(fn (Order $order) => is_numeric($order->order_num) ? (int) $order->order_num : 0)
            ->max();

        return ((int) $max) + 1;
    }

    private function completionDate(string $turnaround, \Illuminate\Support\Carbon $submittedAt): string
    {
        $hours = match (strtolower($turnaround)) {
            'superrush' => 8,
            'priority' => 12,
            default => 24,
        };

        return $submittedAt->copy()->addHours($hours)->format('Y-m-d H:i:s');
    }

    private function storeSourceFiles(array $files, Order $order, string $fileSource): void
    {
        $submittedAt = now();
        $timestamp = $submittedAt->format('Y-m-d H:i:s');

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $folder = $fileSource === 'quote' ? 'quotes' : 'order';
            $originalName = $this->cleanFileName((string) $file->getClientOriginalName());
            $storedName = $submittedAt->format('Y-m-d_His').'_('.$order->order_id.')_'.$originalName;
            $storedPath = SharedUploads::storeUploadedFile($file, $folder, $storedName, $submittedAt);

            Attachment::query()->create([
                'order_id' => $order->order_id,
                'file_name' => $originalName,
                'file_name_with_date' => $storedPath,
                'file_name_with_order_id' => '('.$order->order_id.') '.$originalName,
                'file_source' => $fileSource,
                'date_added' => $timestamp,
            ]);
        }
    }

    private function cleanFileName(string $fileName): string
    {
        $clean = UploadFileName::sanitize($fileName);

        return $clean !== '' ? $clean : 'upload-'.Str::random(8);
    }

    private function customer(Request $request): AdminUser
    {
        return $request->attributes->get('customerUser');
    }

    private function site(Request $request): SiteContext
    {
        return $request->attributes->get('siteContext');
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function normalizedAppliqueValues(array $validated): array
    {
        $appliques = strtolower(trim((string) ($validated['appliques'] ?? 'no'))) === 'yes' ? 'yes' : 'no';

        if ($appliques !== 'yes') {
            return ['no', 0, ''];
        }

        return [
            'yes',
            (int) ($validated['no_of_appliques'] ?? 0),
            trim((string) ($validated['applique_colors'] ?? '')),
        ];
    }

    private function sendAdminAlertForSubmission(AdminUser $customer, SiteContext $site, Order $order, array $flow): void
    {
        $submissionLabel = match ((string) ($flow['order_type'] ?? 'order')) {
            'q-vector' => 'New Vector Quote',
            'digitzing' => 'New Digitizing Quote',
            'vector' => 'New Vector Order',
            default => 'New Digitizing Order',
        };

        $this->sendAdminAlertForAction($customer, $site, $order, $submissionLabel.' Submitted');
    }

    private function sendCustomerSubmissionConfirmation(AdminUser $customer, SiteContext $site, Order $order, array $flow): void
    {
        $recipient = PortalMailer::normalizeRecipient((string) $customer->user_email);

        if ($recipient === null) {
            return;
        }

        [$templateKey, $defaultSubject] = match ((string) ($flow['order_type'] ?? 'order')) {
            'q-vector' => ['customer_vector_quote_confirmation', 'Your vector quote has been received'],
            'digitzing' => ['customer_digitizing_quote_confirmation', 'Your digitizing quote has been received'],
            'vector' => ['customer_vector_order_confirmation', 'Your vector order has been received'],
            default => ['customer_digitizing_order_confirmation', 'Your digitizing order has been received'],
        };

        $destinationUrl = $this->customerDestinationUrl($site, (string) $flow['success_redirect']);

        SystemEmailTemplates::send(
            $recipient,
            $templateKey,
            $site,
            [
                'customer_name' => trim((string) ($customer->display_name ?: $customer->user_name)),
                'customer_email' => (string) $customer->user_email,
                'order_id' => (string) $order->order_id,
                'design_name' => (string) $order->design_name,
                'order_type' => (string) $flow['page_title'],
                'format' => (string) ($order->format ?? ''),
                'turnaround' => (string) ($order->turn_around_time ?? ''),
                'orders_url' => $this->customerDestinationUrl($site, '/view-orders.php'),
                'quotes_url' => $this->customerDestinationUrl($site, '/view-quotes.php'),
                'portal_url' => $this->customerDestinationUrl($site, '/dashboard.php'),
                'review_url' => $destinationUrl,
            ],
            fn () => [
                'subject' => $defaultSubject.' - '.$site->displayLabel(),
                'body' => $this->defaultCustomerSubmissionBody($customer, $site, $order, $flow, $destinationUrl),
            ]
        );
    }

    private function sendAdminAlertForAction(AdminUser $customer, SiteContext $site, Order $order, string $subject, string $comment = ''): void
    {
        $recipient = (string) config('mail.admin_alert_address', $site->supportEmail);

        if (PortalMailer::normalizeRecipient($recipient) === null) {
            return;
        }

        $customerName = trim((string) ($customer->display_name ?: $customer->user_name));
        $detailUrl = $this->adminDetailUrl($order);
        $designName = e((string) $order->design_name);
        $customerLabel = e($customerName);
        $customerEmail = e((string) ($customer->user_email ?? ''));
        $format = e((string) ($order->format ?? ''));
        $turnaround = e((string) ($order->turn_around_time ?? ''));
        $subjectLabel = e($subject);
        $commentHtml = trim($comment) !== '' ? '<p><strong>Customer Notes:</strong> '.e($comment).'</p>' : '';

        $body = <<<HTML
<p>{$subjectLabel} on {$site->displayLabel()} <strong>(Legacy Platform)</strong>.</p>
<p><strong>Order ID:</strong> {$order->order_id}</p>
<p><strong>Design Name:</strong> {$designName}</p>
<p><strong>Customer:</strong> {$customerLabel}</p>
<p><strong>Email:</strong> {$customerEmail}</p>
<p><strong>Format:</strong> {$format}</p>
<p><strong>Turnaround:</strong> {$turnaround}</p>
{$commentHtml}
<p><a href="{$detailUrl}">Open order detail</a></p>
HTML;

        PortalMailer::sendHtml($recipient, '[Legacy] '.$subject, $body);
    }

    private function adminDetailUrl(Order $order): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $path = '/v/view-order-detail.php?order_id='.$order->order_id.'&back=new-orders';

        return $baseUrl !== '' ? $baseUrl.$path : $path;
    }

    private function customerDestinationUrl(SiteContext $site, string $path): string
    {
        $baseUrl = rtrim((string) ($site->websiteAddress ?: config('app.url', '')), '/');

        return $baseUrl !== '' ? $baseUrl.$path : $path;
    }

    private function defaultCustomerSubmissionBody(AdminUser $customer, SiteContext $site, Order $order, array $flow, string $destinationUrl): string
    {
        $customerName = e(trim((string) ($customer->display_name ?: $customer->user_name)));
        $flowLabel = e((string) $flow['page_title']);
        $designName = e((string) $order->design_name);
        $format = e((string) ($order->format ?? ''));
        $turnaround = e((string) ($order->turn_around_time ?? ''));

        return <<<HTML
<p>Hello {$customerName},</p>
<p>We received your {$flowLabel} and it is now in our workflow.</p>
<p><strong>Reference ID:</strong> {$order->order_id}</p>
<p><strong>Design Name:</strong> {$designName}</p>
<p><strong>Format:</strong> {$format}</p>
<p><strong>Turnaround:</strong> {$turnaround}</p>
<p>You can review the latest status in your account here: <a href="{$destinationUrl}">Open your account</a></p>
<p>Thank you,<br>{$site->displayLabel()}</p>
HTML;
    }
}
