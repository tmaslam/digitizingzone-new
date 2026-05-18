<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderComment;
use App\Support\AdminNavigation;
use App\Support\AttachmentPreview;
use App\Support\PortalMailer;
use App\Support\SharedUploads;
use App\Support\SiteResolver;
use App\Support\SystemEmailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminQuickQuoteController extends Controller
{
    public function show(Request $request)
    {
        abort_unless(Schema::hasTable('qucik_quote_users'), 404);

        $orderId = (int) $request->query('oid');
        $order = Order::query()->findOrFail($orderId);
        $quoteCustomer = DB::table('qucik_quote_users')->where('customer_oid', $orderId)->first();
        $site = SiteResolver::fromLegacyKey((string) ($order->website ?: config('sites.primary_legacy_key', '1dollar')))
            ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        return view('admin.tools.quick-quote-detail', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'order' => $order,
            'quoteCustomer' => $quoteCustomer,
            'customerComments' => OrderComment::query()->where('order_id', $orderId)->where('comment_source', 'customerComments')->where('comments', '!=', '')->latest('id')->get(),
            'adminComments' => OrderComment::query()->where('order_id', $orderId)->whereIn('comment_source', ['admin', 'customer'])->latest('id')->get(),
            'teamComments' => OrderComment::query()->where('order_id', $orderId)->where('comment_source', 'team')->latest('id')->get(),
            'canCompleteQuote' => $this->canCompleteFromAdmin($order),
            'completionEmailTemplateOptions' => SystemEmailTemplates::selectionOptions($site, 'customer_quick_quote_completed'),
            'attachmentGroups' => [
                'complaint' => Attachment::query()->where('order_id', $orderId)->where('file_source', 'edit order')->orderByDesc('id')->get(),
                'order' => Attachment::query()->where('order_id', $orderId)->whereIn('file_source', ['qquote', 'vector', 'q-order'])->orderByDesc('id')->get(),
            ],
        ]);
    }

    public function addComment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'comment_source' => ['required', 'in:customer,team'],
            'comments' => ['required', 'string'],
        ], [], [
            'order_id' => 'quote',
            'comment_source' => 'comment type',
            'comments' => 'comment',
        ]);

        $now = now()->format('Y-m-d H:i:s');

        OrderComment::query()->create([
            'order_id' => $validated['order_id'],
            'comments' => $validated['comments'],
            'source_page' => $validated['comment_source'],
            'comment_source' => $validated['comment_source'],
            'date_added' => $now,
            'date_modified' => $now,
        ]);

        return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$validated['order_id'].'&page=qquote'))
            ->with('success', 'Comment added successfully.');
    }

    public function deleteComment(OrderComment $comment)
    {
        $orderId = $comment->order_id;
        $comment->delete();

        return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$orderId.'&page=qquote'))
            ->with('success', 'Comment deleted successfully.');
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $path = $this->attachmentAbsolutePath($attachment);
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->file_name_with_order_id ?: $attachment->file_name ?: basename($path));
    }

    public function previewAttachment(Request $request, Attachment $attachment)
    {
        $path = $this->attachmentAbsolutePath($attachment);
        $displayName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name ?: basename($path));
        abort_unless(AttachmentPreview::isSupported($displayName), 404);

        if ((bool) $request->route('raw') || $request->boolean('raw')) {
            return AttachmentPreview::inlineResponse($path, $displayName);
        }

        return view('admin.attachments.preview', [
            'adminUser' => $request->attributes->get('adminUser'),
            'navCounts' => AdminNavigation::counts(),
            'displayName' => $displayName,
            'previewKind' => AttachmentPreview::kindForFileName($displayName),
            'rawUrl' => url('/v/quick-attachments/'.$attachment->id.'/preview/raw'),
            'downloadUrl' => url('/v/quick-attachments/'.$attachment->id.'/download'),
            'backUrl' => url('/v/view-quick-order-detail.php?oid='.$attachment->order_id.'&page=qquote'),
            'textContent' => AttachmentPreview::kindForFileName($displayName) === 'text'
                ? AttachmentPreview::textContents($path)
                : null,
        ]);
    }

    public function deleteAttachment(Attachment $attachment)
    {
        $path = $this->attachmentAbsolutePath($attachment);
        if (is_file($path)) {
            @unlink($path);
        }

        $orderId = $attachment->order_id;
        $attachment->delete();

        return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$orderId.'&page=qquote'))
            ->with('success', 'Attachment removed successfully.');
    }

    public function complete(Request $request)
    {
        abort_unless(Schema::hasTable('qucik_quote_users'), 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'stitches' => ['required', 'string'],
            'stamount' => ['required', 'string'],
            'ddlStatus' => ['required', 'in:done,Disapproved'],
            'customer_email_template_id' => ['nullable', 'integer'],
        ], [], [
            'order_id' => 'quote',
            'stitches' => 'stitches',
            'stamount' => 'amount',
            'ddlStatus' => 'status',
            'customer_email_template_id' => 'email template',
        ]);

        $order = Order::query()->findOrFail((int) $validated['order_id']);
        abort_unless($order->order_type === 'qquote', 404);
        $stitches = trim((string) $validated['stitches']);

        if (! $this->canCompleteFromAdmin($order)) {
            return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'))
                ->withErrors(['detail' => 'Only ready quick quotes or actively assigned team quick quotes can be completed from this screen.']);
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $stitches)) {
            return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'))
                ->withErrors(['stitches' => 'No. Of Stitches must be a numeric value.']);
        }

        $quoteCustomer = DB::table('qucik_quote_users')->where('customer_oid', $order->order_id)->first();

        $amount = $validated['stamount'];

        $status = $validated['ddlStatus'] === 'Disapproved' ? 'disapproved' : 'done';

        $order->update([
            'completion_date' => now()->format('Y-m-d H:i:s'),
            'stitches_price' => $amount,
            'status' => $status,
            'total_amount' => $amount,
            'stitches' => $stitches,
        ]);

        if ($status === 'done' && $quoteCustomer?->customer_email) {
            $site = SiteResolver::fromLegacyKey((string) ($order->website ?: config('sites.primary_legacy_key', '1dollar')))
                ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));
            $this->sendQuoteMail(
                (string) $quoteCustomer->customer_email,
                $order,
                $stitches,
                (string) $amount,
                SystemEmailTemplates::selectedTemplateOverride(
                    $site,
                    'customer_quick_quote_completed',
                    isset($validated['customer_email_template_id']) ? (int) $validated['customer_email_template_id'] : null
                )
            );
        }

        return redirect()->to(url('/v/view-quick-order-detail.php?oid='.$order->order_id.'&page=qquote'))
            ->with('success', $status === 'done' ? 'Quotation completed successfully.' : 'Quotation disapproved successfully.');
    }

    private function attachmentAbsolutePath(Attachment $attachment): string
    {
        $folder = match ($attachment->file_source) {
            'qquote', 'edit order' => 'quotes',
            'scanned' => 'scanned',
            'team', 'orderTeamImages' => 'team',
            'sewout' => 'sewout',
            default => 'order',
        };

        return SharedUploads::path($folder.DIRECTORY_SEPARATOR.$attachment->file_name_with_date);
    }

    private function sendQuoteMail(string $email, Order $order, string $stitches, string $amount, ?array $override = null): void
    {
        $site = SiteResolver::fromLegacyKey((string) ($order->website ?: config('sites.primary_legacy_key', '1dollar')))
            ?? SiteResolver::fromHost((string) config('sites.primary_host', 'localhost'));

        SystemEmailTemplates::send(
            $email,
            'customer_quick_quote_completed',
            $site,
            [
                'design_name' => (string) ($order->design_name ?? ''),
                'order_id' => (string) $order->order_id,
                'amount' => (string) $amount,
                'stitches' => (string) $stitches,
                'payment_url' => url('/instant-payment.php'),
            ],
            fn () => [
                'subject' => 'Quote request for '.$order->design_name,
                'body' => view('admin.tools.mail-quick-quote', [
                    'order' => $order,
                    'stitches' => $stitches,
                    'amount' => $amount,
                    'paymentUrl' => url('/instant-payment.php'),
                ])->render(),
            ],
            $override
        );
    }

    private function canCompleteFromAdmin(Order $order): bool
    {
        $status = strtolower(trim((string) $order->status));

        if ($status === 'ready') {
            return true;
        }

        $isAssignedToTeam = ! in_array((string) $order->assign_to, ['', '0'], true);
        if (! $isAssignedToTeam) {
            return false;
        }

        return ! in_array($status, ['done', 'approved', 'disapproved'], true);
    }
}
