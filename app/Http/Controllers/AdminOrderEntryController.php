<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\Attachment;
use App\Models\Billing;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\Site;
use App\Support\AdminNavigation;
use App\Support\OrderWorkflow;
use App\Support\OrderWorkflowMetaManager;
use App\Support\PricingResolver;
use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use App\Support\SiteResolver;
use App\Support\UploadFileName;
use App\Support\UploadSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminOrderEntryController extends Controller
{
    private const DIGITIZING_FORMAT_OPTIONS = ['DST', 'PES', 'EXP', 'EMB', 'PXF', 'NGS', 'other'];

    private const VECTOR_FORMAT_OPTIONS = ['AI', 'EPS', 'PSD', 'PDF', 'SVG', 'other'];

    private const TURNAROUND_OPTIONS = ['Standard', 'Priority', 'Superrush'];

    public function create(Request $request)
    {
        $customer = null;
        $customerId = (int) old('customer_user_id', (int) $request->query('customer_user_id', 0));
        $selectedWebsite = trim((string) old('website', (string) $request->query('website', '')));

        if ($customerId > 0) {
            $customer = AdminUser::query()->customers()->active()->find($customerId);

            if ($selectedWebsite === '') {
                $selectedWebsite = trim((string) ($customer?->website ?: ''));
            }
        }

        if ($selectedWebsite === '') {
            $selectedWebsite = SiteResolver::forRequest($request)->legacyKey;
        }

        $sites = $this->siteOptions();
        $customerOptions = AdminUser::query()
            ->customers()
            ->active()
            ->orderBy('user_name')
            ->get(['user_id', 'user_name', 'first_name', 'last_name', 'user_email', 'website'])
            ->map(function (AdminUser $customerOption) {
                return [
                    'user_id' => (int) $customerOption->user_id,
                    'display_name' => $customerOption->display_name,
                    'email' => (string) ($customerOption->user_email ?: ''),
                    'website' => trim((string) ($customerOption->website ?: config('sites.primary_legacy_key', '1dollar'))),
                ];
            })
            ->values();

        return view('admin.orders.create', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'customer' => $customer,
            'customerOptions' => $customerOptions,
            'hasWorkflowMetaTable' => OrderWorkflowMetaManager::hasTable(),
            'selectedWebsite' => $selectedWebsite,
            'sites' => $sites,
            'turnaroundOptions' => self::TURNAROUND_OPTIONS,
            'digitizingFormatOptions' => self::DIGITIZING_FORMAT_OPTIONS,
            'vectorFormatOptions' => self::VECTOR_FORMAT_OPTIONS,
            'sourceFileAccept' => UploadSecurity::acceptAttribute('source'),
        ]);
    }

    public function store(Request $request)
    {
        if (! OrderWorkflowMetaManager::hasTable()) {
            return back()->withErrors([
                'workflow' => 'This feature requires the `order_workflow_meta` table. Please run the SQL file first.',
            ])->withInput();
        }

        $validated = $request->validate([
            'entry_stage' => ['required', 'in:new,completed_unpaid,completed_paid'],
            'flow_context' => ['required', 'in:order,code'],
            'work_type' => ['required', 'in:digitizing,vector'],
            'website' => ['required', 'string', 'max:30'],
            'customer_user_id' => ['required', 'integer'],
            'design_name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:255'],
            'fabric_type' => ['nullable', 'string', 'max:255'],
            'sew_out' => ['nullable', 'in:yes,no'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'measurement' => ['nullable', 'in:Inches,CM,MM'],
            'turn_around_time' => ['nullable', 'string', 'max:150'],
            'no_of_colors' => ['nullable', 'integer', 'min:0'],
            'color_names' => ['nullable', 'string'],
            'appliques' => ['nullable', 'in:yes,no'],
            'no_of_appliques' => ['nullable', 'integer', 'min:0'],
            'applique_colors' => ['nullable', 'string', 'max:255'],
            'starting_point' => ['nullable', 'in:TopLeft,TopCenter,TopRight,MiddleLeft,Center,MiddleRight,BottomLeft,BottomCenter,BottomRight'],
            'customer_notes' => ['nullable', 'string'],
            'additional_details' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'stitches' => ['nullable', 'string', 'max:255'],
            'submitted_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'required_if:entry_stage,completed_unpaid,completed_paid'],
            'order_credit_limit' => ['nullable', 'numeric', 'min:0'],
            'delivery_override' => ['nullable', 'in:auto,preview_only'],
            'source_files.*' => ['nullable', 'file', 'max:30720'],
            'completed_files.*' => ['nullable', 'file', 'max:30720'],
        ], [
            'source_files.*.max' => 'Each source file must be 30 MB or smaller.',
            'completed_files.*.max' => 'Each completed file must be 30 MB or smaller.',
        ], [
            'entry_stage' => 'record stage',
            'flow_context' => 'workflow',
            'work_type' => 'work type',
            'website' => 'website',
            'customer_user_id' => 'customer user ID',
            'design_name' => 'design name',
            'subject' => 'subject',
            'format' => 'format',
            'fabric_type' => 'fabric type',
            'sew_out' => 'sew out',
            'width' => 'width',
            'height' => 'height',
            'measurement' => 'measurement',
            'turn_around_time' => 'turnaround time',
            'no_of_colors' => 'number of colors',
            'color_names' => 'color names',
            'appliques' => 'appliques',
            'no_of_appliques' => 'number of appliques',
            'applique_colors' => 'applique colors',
            'starting_point' => 'starting point',
            'customer_notes' => 'customer notes',
            'additional_details' => 'additional details',
            'admin_note' => 'admin note',
            'amount' => 'final amount',
            'stitches' => 'stitches or hours',
            'submitted_at' => 'submitted date',
            'completed_at' => 'completed / delivered date',
            'order_credit_limit' => 'order credit limit',
            'delivery_override' => 'customer file access',
            'source_files.*' => 'source file',
            'completed_files.*' => 'completed file',
        ]);

        $customer = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->forWebsite($validated['website'])
            ->find($validated['customer_user_id']);

        if (! $customer) {
            return back()->withErrors(['customer_user_id' => 'The selected active customer was not found for the chosen website.'])->withInput();
        }

        $uploadValidationError = UploadSecurity::assertAllowedFiles($request->file('source_files', []), 'source');
        if ($uploadValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, 'source', 'Admin source upload was rejected during create order / quote.', [
                'website' => $validated['website'],
                'file_names' => collect($request->file('source_files', []))
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return back()->withErrors(['source_files' => $uploadValidationError])->withInput();
        }
        $completedFilesValidationError = UploadSecurity::assertAllowedFiles($request->file('completed_files', []), 'production');
        if ($completedFilesValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, 'production', 'Admin completed-file upload was rejected during create order / quote.', [
                'website' => $validated['website'],
                'file_names' => collect($request->file('completed_files', []))
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return back()->withErrors(['completed_files' => $completedFilesValidationError])->withInput();
        }

        $adminUser = $request->attributes->get('adminUser');
        $mapping = OrderWorkflow::createTypeMapping((string) $validated['flow_context'], (string) $validated['work_type']);
        $now = now();
        $entryStage = (string) $validated['entry_stage'];
        $isCompletedPaidEntry = $entryStage === 'completed_paid';
        $isCompletedUnpaidEntry = $entryStage === 'completed_unpaid';
        $isCompletedEntry = in_array($entryStage, ['completed_unpaid', 'completed_paid'], true);
        $submittedAt = $this->normalizeDateTime($validated['submitted_at'] ?? null, $now->format('Y-m-d H:i:s'));
        $completedAt = $this->normalizeDateTime($validated['completed_at'] ?? null, $now->format('Y-m-d H:i:s'));
        $subject = trim((string) ($validated['subject'] ?? ''));
        $subject = $subject !== '' ? $subject : (string) $validated['design_name'];
        $stitches = trim((string) ($validated['stitches'] ?? ''));
        $amount = $request->filled('amount') ? round((float) $validated['amount'], 2) : 0.0;

        if ($isCompletedEntry) {
            [$stitches, $amount, $calculationError] = $this->resolveCompletedEntryPricing(
                $request,
                $customer,
                (string) $validated['flow_context'],
                (string) $validated['work_type'],
                (string) ($validated['turn_around_time'] ?? 'Normal'),
                $stitches,
                $amount
            );

            if ($calculationError !== null) {
                return back()->withErrors(['amount' => $calculationError])->withInput();
            }
        }

        [$appliques, $noOfAppliques, $appliqueColors] = $this->normalizedAppliqueValues($validated);

        $order = Order::query()->create([
            'user_id' => $customer->user_id,
            'order_num' => 'ADM-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4)),
            'design_name' => $validated['design_name'],
            'format' => $validated['format'] ?? '',
            'fabric_type' => $validated['fabric_type'] ?? '',
            'sew_out' => $validated['sew_out'] ?? 'no',
            'width' => $request->filled('width') ? (string) $validated['width'] : '',
            'height' => $request->filled('height') ? (string) $validated['height'] : '',
            'measurement' => $validated['measurement'] ?? '',
            'no_of_colors' => (int) ($validated['no_of_colors'] ?? 0),
            'color_names' => $validated['color_names'] ?? '',
            'appliques' => $appliques,
            'no_of_appliques' => $noOfAppliques,
            'applique_colors' => $appliqueColors,
            'starting_point' => trim((string) ($validated['starting_point'] ?? '')),
            'comments1' => $validated['customer_notes'] ?? '',
            'comments2' => $validated['additional_details'] ?? '',
            'status' => $isCompletedEntry ? 'approved' : 'Underprocess',
            'stitches_price' => number_format($amount, 2, '.', ''),
            'total_amount' => number_format($amount, 2, '.', ''),
            'turn_around_time' => $validated['turn_around_time'] ?? 'Normal',
            'submit_date' => $submittedAt,
            'modified_date' => $now->format('Y-m-d H:i:s'),
            'completion_date' => $isCompletedEntry ? $completedAt : null,
            'assigned_date' => $isCompletedEntry ? $completedAt : null,
            'vender_complete_date' => $isCompletedEntry ? $completedAt : null,
            'stitches' => $stitches,
            'assign_to' => 0,
            'subject' => $subject,
            'is_active' => 1,
            'order_type' => $mapping['order_type'],
            'order_status' => '',
            'advance_pay' => '0',
            'website' => $validated['website'],
            'notes_by_user' => trim((string) ($validated['customer_notes'] ?? '')) !== '' ? 1 : 0,
            'notes_by_admin' => 0,
            'sent' => 'Normal',
            'working' => '',
            'del_attachment' => 0,
            'type' => $mapping['type'],
        ]);

        if (filled($validated['admin_note'] ?? null)) {
            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => trim((string) $validated['admin_note']),
                'source_page' => 'admin',
                'comment_source' => 'admin',
                'date_added' => $now->format('Y-m-d H:i:s'),
                'date_modified' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        $this->storeSourceFiles($request->file('source_files', []), $order, (string) $mapping['source_file_source']);
        $this->storeCompletedFiles($request->file('completed_files', []), $order, $entryStage);

        if ($isCompletedPaidEntry) {
            Billing::query()->create([
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'approved' => 'yes',
                'amount' => (string) ($order->total_amount ?: '0.00'),
                'earned_amount' => '',
                'payment' => 'yes',
                'approve_date' => $completedAt,
                'comments' => 'Admin data-entry record created as completed, delivered, and paid.',
                'transid' => 'admin-entry-'.$order->order_id,
                'trandtime' => $completedAt,
                'website' => $order->website ?: '1dollar',
                'is_paid' => 1,
                'is_advance' => 0,
                'deleted_by' => null,
                'end_date' => null,
            ]);
        } elseif ($isCompletedUnpaidEntry) {
            Billing::query()->create([
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'approved' => 'yes',
                'amount' => (string) ($order->total_amount ?: '0.00'),
                'earned_amount' => '',
                'payment' => 'no',
                'approve_date' => $completedAt,
                'comments' => 'Admin data-entry record created as completed and delivered. Payment is still pending.',
                'transid' => '',
                'trandtime' => null,
                'website' => $order->website ?: '1dollar',
                'is_paid' => 0,
                'is_advance' => 0,
                'deleted_by' => null,
                'end_date' => null,
            ]);
        }

        OrderWorkflowMetaManager::ensure($order, [
            'created_source' => $isCompletedEntry ? 'admin_backfill' : 'admin_assisted',
            'historical_backfill' => $isCompletedEntry ? 1 : 0,
            'suppress_customer_notifications' => $isCompletedEntry ? 1 : 0,
            'delivery_override' => $isCompletedPaidEntry ? 'auto' : (string) ($validated['delivery_override'] ?? 'auto'),
            'order_credit_limit' => $request->filled('order_credit_limit') ? round((float) $validated['order_credit_limit'], 2) : null,
            'created_by_user_id' => $adminUser?->user_id,
            'created_by_name' => $adminUser?->user_name ?: 'admin',
        ]);

        $detailPage = $mapping['page'];

        return redirect()->to(url('/v/orders/'.$order->order_id.'/detail/'.$detailPage.'?'.http_build_query([
            'back' => $this->backPageForCreatedEntry($detailPage, $entryStage),
        ])))->with('success', match (true) {
            $isCompletedPaidEntry => 'Completed paid record created successfully.',
            $isCompletedUnpaidEntry => 'Completed unpaid record created successfully.',
            default => 'Admin-assisted order created successfully.',
        });
    }

    public function previewPrice(Request $request)
    {
        $validated = $request->validate([
            'flow_context' => ['required', 'in:order,code'],
            'work_type' => ['required', 'in:digitizing,vector'],
            'website' => ['required', 'string', 'max:30'],
            'customer_user_id' => ['required', 'integer'],
            'turn_around_time' => ['nullable', 'string', 'max:150'],
            'stitches' => ['required', 'string', 'max:255'],
        ], [], [
            'flow_context' => 'queue',
            'work_type' => 'work type',
            'website' => 'website',
            'customer_user_id' => 'customer user ID',
            'turn_around_time' => 'turnaround',
            'stitches' => 'stitches or hours',
        ]);

        $customer = AdminUser::query()
            ->customers()
            ->active()
            ->where('is_active', 1)
            ->forWebsite($validated['website'])
            ->find($validated['customer_user_id']);

        if (! $customer) {
            return response()->json([
                'message' => 'The selected customer user ID was not found.',
            ], 422);
        }

        $result = PricingResolver::forAdminEntry(
            $customer,
            (string) $validated['flow_context'],
            (string) $validated['work_type'],
            (string) ($validated['turn_around_time'] ?? 'Normal'),
            (string) $validated['stitches']
        );

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'stitches' => $result['units'],
            'amount' => number_format((float) $result['amount'], 2, '.', ''),
        ]);
    }

    private function storeSourceFiles(array $files, Order $order, string $fileSource): void
    {
        if ($files === []) {
            return;
        }

        $submittedAt = now();
        $prefix = $submittedAt->format('Y-m-d G-i');
        $folder = $this->uploadFolderForSource($fileSource);
        $timestamp = $submittedAt->format('Y-m-d H:i:s');

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $original = $this->cleanFileName($file->getClientOriginalName());
            $storedName = $prefix.'_(('.$order->order_id.'))_'.$original;
            $displayName = '('.$order->order_id.') '.$original;

            $storedPath = SharedUploads::storeUploadedFile($file, $folder, $storedName, $submittedAt);

            Attachment::query()->create([
                'order_id' => $order->order_id,
                'file_name' => $original,
                'file_name_with_date' => $storedPath,
                'file_name_with_order_id' => $displayName,
                'file_source' => $fileSource,
                'date_added' => $timestamp,
            ]);
        }
    }

    private function uploadFolderForSource(string $fileSource): string
    {
        return match ($fileSource) {
            'quote' => 'quotes',
            'vector', 'color' => 'order',
            default => 'order',
        };
    }

    private function cleanFileName(string $fileName): string
    {
        $clean = UploadFileName::sanitize($fileName);

        return trim($clean) !== '' ? $clean : Str::random(12);
    }

    private function normalizeDateTime(?string $value, ?string $default): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $default;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $default;
        }
    }

    private function storeCompletedFiles(array $files, Order $order, string $entryStage): void
    {
        if ($files === []) {
            return;
        }

        $fileSource = match ($entryStage) {
            'completed_paid', 'completed_unpaid' => 'team',
            default => null,
        };

        if ($fileSource === null) {
            return;
        }

        $submittedAt = now();
        $prefix = $submittedAt->format('Y-m-d G-i');
        $folder = 'team';
        $timestamp = $submittedAt->format('Y-m-d H:i:s');

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $original = $this->cleanFileName($file->getClientOriginalName());
            $storedName = $prefix.'_(('.$order->order_id.'))_'.$original;
            $displayName = '('.$order->order_id.') '.$original;

            $storedPath = SharedUploads::storeUploadedFile($file, $folder, $storedName, $submittedAt);

            Attachment::query()->create([
                'order_id' => $order->order_id,
                'file_name' => $original,
                'file_name_with_date' => $storedPath,
                'file_name_with_order_id' => $displayName,
                'file_source' => $fileSource,
                'date_added' => $timestamp,
            ]);
        }
    }

    private function backPageForCreatedEntry(string $detailPage, string $entryStage): string
    {
        if ($entryStage === 'new') {
            return in_array($detailPage, ['quote', 'vector'], true) ? 'new-quotes' : 'new-orders';
        }

        return 'all-orders';
    }

    private function resolveCompletedEntryPricing(
        Request $request,
        AdminUser $customer,
        string $flowContext,
        string $workType,
        string $turnaroundTime,
        string $stitches,
        float $amount
    ): array {
        $result = PricingResolver::forAdminEntry(
            $customer,
            $flowContext,
            $workType,
            $turnaroundTime,
            $stitches
        );

        if (! $result['ok']) {
            if ($request->filled('amount')) {
                return [$stitches, $amount, null];
            }

            return [$stitches, $amount, $result['message']];
        }

        return [
            (string) $result['units'],
            $request->filled('amount') ? $amount : (float) $result['amount'],
            null,
        ];
    }

    private function siteOptions(): array
    {
        if (Schema::hasTable('sites')) {
            return Site::query()
                ->active()
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(['legacy_key', 'name', 'brand_name'])
                ->map(fn (Site $site) => [
                    'legacy_key' => (string) $site->legacy_key,
                    'label' => (string) ($site->brand_name ?: $site->name ?: $site->legacy_key),
                ])
                ->values()
                ->all();
        }

        $site = SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        return [[
            'legacy_key' => $site->legacyKey,
            'label' => $site->brandName,
        ]];
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

}
