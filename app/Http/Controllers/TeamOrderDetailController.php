<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\QuoteNegotiation;
use Illuminate\Support\Facades\Schema;
use App\Support\AttachmentPreview;
use App\Support\DownstreamSharing;
use App\Support\PortalMailer;
use App\Support\OrderWorkflowMetaManager;
use App\Support\PricingResolver;
use App\Support\SecurityAudit;
use App\Support\SharedUploads;
use App\Support\TeamAccess;
use App\Support\TeamNavigation;
use App\Support\TeamPricing;
use App\Support\TeamWorkQueues;
use App\Support\UploadSecurity;
use App\Support\UploadFileName;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamOrderDetailController extends Controller
{
    public function showByRoute(Request $request, Order $order, ?string $mode = null)
    {
        $request->query->set('oid', (string) $order->order_id);
        $request->query->set('act', TeamWorkQueues::normalizeMode($mode));

        return $this->show($request);
    }

    public function show(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrder($request, $teamUser);
        abort_if($order->order_type === 'qquote', 404);

        $mode = $this->modeFromRequest($request, $order);
        $queueKey = $this->queueKeyFromRequest($request, $mode);
        $editComment = $this->editableComment($request->query('edit_comment'), $order->order_id);
        $navCounts = TeamNavigation::counts($teamUser->user_id, (int) $teamUser->usre_type_id);

        $isPriceNegotiated = Schema::hasTable('quote_negotiations')
            && QuoteNegotiation::query()->where('order_id', $order->order_id)->where('status', 'accepted_by_admin')->exists();
        $hidePriceForTeam = $isPriceNegotiated || OrderWorkflowMetaManager::isQuoteConverted($order);

        return view('team.orders.show', [
            'teamUser' => $teamUser,
            'navCounts' => $navCounts,
            'order' => $order,
            'mode' => $mode,
            'queueKey' => $queueKey,
            'backUrl' => $this->backUrl($queueKey),
            'backLabel' => TeamWorkQueues::label($queueKey),
            'selfUrl' => $this->detailUrl($order, $mode, $queueKey),
            'sharedComments' => DownstreamSharing::sharedComments($order->order_id),
            'teamComments' => OrderComment::query()->where('order_id', $order->order_id)->where('comment_source', 'team')->latest('id')->get(),
            'sharedAttachments' => Attachment::query()->where('order_id', $order->order_id)->where('file_source', 'orderTeamImages')->orderBy('id')->get(),
            'teamAttachments' => Attachment::query()->where('order_id', $order->order_id)->whereIn('file_source', ['team', 'sewout'])->orderBy('id')->get(),
            'teamCanComplete' => $mode === 'quote' || Attachment::query()->where('order_id', $order->order_id)->whereIn('file_source', ['team', 'sewout'])->exists(),
            'isPriceNegotiated' => $isPriceNegotiated,
            'hidePriceForTeam' => $hidePriceForTeam,
            'stitchLabel' => $this->stitchLabel($order),
            'editComment' => $editComment,
            'supervisorReviewComment' => OrderComment::query()->where('order_id', $order->order_id)->where('comment_source', 'supervisorReview')->latest('id')->first(),
            'queueNavigation' => TeamWorkQueues::navigation($navCounts),
            'currentQueueKey' => $queueKey,
        ]);
    }

    public function saveComment(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_if($order->order_type === 'qquote', 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'mode' => ['required', 'string'],
            'queue' => ['nullable', 'string'],
            'comment_id' => ['nullable', 'integer'],
            'comments' => ['required', 'string'],
        ], [], [
            'order_id' => 'order',
            'mode' => 'page',
            'queue' => 'queue',
            'comment_id' => 'comment',
            'comments' => 'comment',
        ]);

        $now = now()->format('Y-m-d H:i:s');
        $commentId = (int) ($validated['comment_id'] ?? 0);

        if ($commentId > 0) {
            $comment = OrderComment::query()
                ->where('id', $commentId)
                ->where('order_id', $order->order_id)
                ->where('comment_source', 'team')
                ->firstOrFail();

            $comment->update([
                'comments' => $validated['comments'],
                'date_modified' => $now,
            ]);

            $message = 'Comment updated successfully.';
        } else {
            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => $validated['comments'],
                'source_page' => $validated['mode'],
                'comment_source' => 'team',
                'date_added' => $now,
                'date_modified' => $now,
            ]);

            $message = 'Comment added successfully.';
        }

        return redirect()->to($this->detailUrl($order, (string) $validated['mode'], (string) ($validated['queue'] ?? '')))
            ->with('success', $message);
    }

    public function deleteComment(Request $request, OrderComment $comment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $comment->order_id, $teamUser);
        abort_if($order->order_type === 'qquote', 404);
        abort_unless($comment->comment_source === 'team', 404);

        $mode = (string) $request->query('mode', 'UnderProcess');
        $queue = (string) $request->query('queue', '');
        $comment->delete();

        return redirect()->to($this->detailUrl($order, $mode, $queue))
            ->with('success', 'Comment deleted successfully.');
    }

    public function uploadAttachment(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_if($order->order_type === 'qquote', 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'mode' => ['required', 'string'],
            'queue' => ['nullable', 'string'],
            'files.*' => ['required', 'file', 'max:30720'],
        ], [
            'files.*.max' => 'Each file must be 30 MB or smaller.',
        ], [
            'order_id' => 'order',
            'mode' => 'page',
            'queue' => 'queue',
            'files' => 'files',
            'files.*' => 'file',
        ]);

        $files = $request->file('files', []);
        if ($files === []) {
            return redirect()->to($this->detailUrl($order, (string) $validated['mode'], (string) ($validated['queue'] ?? '')))
                ->with('success', 'No files were selected.');
        }

        $uploadValidationError = UploadSecurity::assertAllowedFiles($files, 'production');
        if ($uploadValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, 'production', 'Team production upload was rejected on an order detail screen.', [
                'order_id' => $order->order_id,
                'mode' => (string) $validated['mode'],
                'file_names' => collect($files)
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return redirect()->to($this->detailUrl($order, (string) $validated['mode'], (string) ($validated['queue'] ?? '')))
                ->withErrors(['files' => $uploadValidationError]);
        }

        $submittedAt = now();
        $submitDate = $submittedAt->format('Y-m-d H:i:s');
        $prefix = $submittedAt->format('Y-m-d Gi');

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $original = $this->cleanFileName($file->getClientOriginalName());
            $storedName = $prefix.'_(('.$order->order_id.'))_'.$original;
            $displayName = '('.$order->order_id.') '.$original;

            $storedPath = SharedUploads::storeUploadedFile($file, 'team', $storedName, $submittedAt);

            Attachment::query()->create([
                'order_id' => $order->order_id,
                'file_name' => $original,
                'file_name_with_date' => $storedPath,
                'file_name_with_order_id' => $displayName,
                'file_source' => 'team',
                'date_added' => $submitDate,
            ]);
        }

        return redirect()->to($this->detailUrl($order, (string) $validated['mode'], (string) ($validated['queue'] ?? '')))
            ->with('success', 'Files uploaded successfully.');
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_if($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['orderTeamImages', 'team', 'sewout'], true), 404);

        $path = $this->attachmentAbsolutePath($attachment, $order, $this->modeFromRequest($request, $order));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->file_name ?: basename($path));
    }

    public function previewAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_if($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['orderTeamImages', 'team', 'sewout'], true), 404);

        $mode = $this->modeFromRequest($request, $order);
        $path = $this->attachmentAbsolutePath($attachment, $order, $mode);
        $displayName = (string) ($attachment->file_name_with_order_id ?: $attachment->file_name ?: basename($path));
        abort_unless(AttachmentPreview::isSupported($displayName), 404);

        if ((bool) $request->route('raw') || $request->boolean('raw')) {
            return AttachmentPreview::inlineResponse($path, $displayName);
        }

        return view('team.attachments.preview', [
            'teamUser' => $teamUser,
            'navCounts' => TeamNavigation::counts($teamUser->user_id, (int) $teamUser->usre_type_id),
            'displayName' => $displayName,
            'previewKind' => AttachmentPreview::kindForFileName($displayName),
            'rawUrl' => url('/team/attachments/'.$attachment->id.'/preview/raw?mode='.rawurlencode($mode)),
            'downloadUrl' => url('/team/attachments/'.$attachment->id.'/download?mode='.rawurlencode($mode)),
            'backUrl' => $this->detailUrl($order, $mode, (string) $request->query('queue', '')),
            'textContent' => AttachmentPreview::kindForFileName($displayName) === 'text'
                ? AttachmentPreview::textContents($path)
                : null,
        ]);
    }

    public function deleteAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_if($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['team', 'sewout'], true), 404);

        $path = $this->attachmentAbsolutePath($attachment, $order, (string) $request->query('mode', 'UnderProcess'));
        if (is_file($path)) {
            @unlink($path);
        }

        $attachment->delete();

        return redirect()->to($this->detailUrl($order, (string) $request->query('mode', 'UnderProcess'), (string) $request->query('queue', '')))
            ->with('success', 'Attachment removed successfully.');
    }

    public function exportDesignInfo(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->query('design_id'), $teamUser);
        abort_if($order->order_type === 'qquote', 404);

        $content = [];
        $content[] = 'Design ID: '.$order->order_id;
        $content[] = '';
        $content[] = 'Design Name: '.$order->design_name;
        $content[] = '';
        $content[] = 'Format: '.$order->format;
        $content[] = '';

        if (in_array((string) $order->order_type, ['order', 'quote', 'digitzing'], true)) {
            $content[] = 'Fabric Type: '.$order->fabric_type;
            $content[] = '';
            $content[] = 'Sew Out Required: '.$order->sew_out;
            $content[] = '';
            $content[] = 'Design Size: (Width) '.($order->width ?? '').' * (Height) '.($order->height ?? '').' '.($order->measurement ?? '');
            $content[] = '';
            $content[] = 'No Of Colors: '.$order->no_of_colors;
            $content[] = '';
            $content[] = 'Colors Names: '.$order->color_names;
            $content[] = '';
            $content[] = 'Applique Required: '.$order->appliques;
            $content[] = '';
            $content[] = 'Number Of Appliques: '.$order->no_of_appliques;
            $content[] = '';
            $content[] = 'Applique Colors: '.$order->applique_colors;
            $content[] = '';
        }

        $content[] = 'Customer Comments:';
        $content[] = '';
        foreach (DownstreamSharing::sharedComments($order->order_id) as $comment) {
            $content[] = (string) $comment->comments;
            $content[] = '';
        }

        if (in_array((string) $order->order_type, ['order', 'quote', 'digitzing'], true)) {
            $content[] = 'Turn Around Time: '.$order->turn_around_time;
            $content[] = '';
        }

        $content[] = 'File download time: '.now()->format('F j, Y, g:i a');
        $content[] = '';

        return response()->streamDownload(function () use ($content) {
            echo implode("\r\n", $content);
        }, $order->order_id.'.txt', [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public function complete(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_if($order->order_type === 'qquote', 404);

        if (in_array((string) $order->order_type, ['vector', 'q-vector', 'color', 'qcolor'], true)) {
            $composedHours = TeamPricing::composeHours(
                $request->input('work_hours'),
                $request->input('work_minutes')
            );

            if ($composedHours !== null) {
                $request->merge(['stitches' => $composedHours]);
            }
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'mode' => ['required', 'string'],
            'queue' => ['nullable', 'string'],
            'stitches' => ['required', 'string'],
        ], [], [
            'order_id' => 'order',
            'mode' => 'page',
            'queue' => 'queue',
            'stitches' => $this->stitchLabel($order),
        ]);

        $mode = (string) $validated['mode'];
        $queue = (string) ($validated['queue'] ?? '');
        $stitches = trim($validated['stitches']);
        $orderType = (string) $order->order_type;

        if (in_array($orderType, ['order', 'quote', 'digitzing'], true) && (float) $stitches <= 0) {
            return redirect()->to($this->detailUrl($order, $mode, $queue))
                ->withErrors([$orderType === 'quote' ? 'quote' : 'order' => 'Please enter stitches in order to complete this job.']);
        }

        if (in_array($orderType, ['order', 'quote', 'digitzing'], true) && ! preg_match('/^\d+(\.\d+)?$/', $stitches)) {
            return redirect()->to($this->detailUrl($order, $mode, $queue))
                ->withErrors(['stitches' => 'No. Of Stitches must be a numeric value.']);
        }

        if (in_array($orderType, ['vector', 'q-vector', 'color', 'qcolor'], true) && ! TeamPricing::isValidHours($stitches)) {
            return redirect()->to($this->detailUrl($order, $mode, $queue))
                ->withErrors(['hours' => 'Please enter total hours as a whole number or HH:MM in order to complete this job.']);
        }

        if (in_array($orderType, ['vector', 'q-vector', 'color', 'qcolor'], true)) {
            $stitches = TeamPricing::normalizeHours($stitches) ?: $stitches;
        }

        $hasTeamImage = Attachment::query()
            ->where('order_id', $order->order_id)
            ->whereIn('file_source', ['team', 'sewout'])
            ->exists();

        if ($mode !== 'quote' && ! $hasTeamImage) {
            return redirect()->to($this->detailUrl($order, $mode, $queue))
                ->withErrors(['files' => 'Please upload at least one team file before completing this job.']);
        }

        $pricing = PricingResolver::forAdminCompletion($order, $stitches);

        if (! $pricing['ok']) {
            return redirect()->to($this->detailUrl($order, $mode, $queue))
                ->withErrors(['stitches' => $pricing['message']]);
        }

        $stitches = (string) $pricing['units'];
        $price = (float) $pricing['amount'];

        $submitDate = now()->format('Y-m-d G:i');
        $order->update(Order::writablePayload([
            'status' => 'Ready',
            'stitches_price' => $price,
            'stitches' => $stitches,
            'vender_complete_date' => $submitDate,
        ]));

        $subject = $mode === 'quote'
            ? 'Quotation completed by '.$teamUser->user_name
            : 'Order completed by '.$teamUser->user_name;

        $this->sendMailToAdmin($teamUser->display_name, $order, $subject);

        return redirect()->to($this->backUrl($queue !== '' ? $queue : $mode))
            ->with('success', $mode === 'quote'
                ? 'You have successfully completed the quotation.'
                : 'You have successfully completed the order.');
    }

    private function teamOrder(Request $request, AdminUser $teamUser): Order
    {
        return $this->teamOrderById((int) $request->query('oid'), $teamUser);
    }

    private function teamOrderById(int $orderId, AdminUser $teamUser): Order
    {
        return Order::query()
            ->with('customer')
            ->active()
            ->where('order_id', $orderId)
            ->whereIn('assign_to', TeamAccess::accessibleUserIds($teamUser))
            ->firstOrFail();
    }

    private function modeFromRequest(Request $request, Order $order): string
    {
        return TeamWorkQueues::normalizeMode((string) $request->query('act', TeamWorkQueues::detailModeForOrder($order)));
    }

    private function editableComment(mixed $commentId, int $orderId): ?OrderComment
    {
        if (! $commentId) {
            return null;
        }

        return OrderComment::query()
            ->where('id', (int) $commentId)
            ->where('order_id', $orderId)
            ->where('comment_source', 'team')
            ->first();
    }

    private function detailUrl(Order $order, string $mode, string $queue = ''): string
    {
        return TeamWorkQueues::detailUrl($order, $mode, $queue);
    }

    private function backUrl(string $mode): string
    {
        return TeamWorkQueues::backUrl($mode);
    }

    private function queueKeyForMode(string $mode): string
    {
        return match (TeamWorkQueues::normalizeMode($mode)) {
            'quote' => 'quotes',
            'disapproved' => 'disapproved-orders',
            default => 'new-orders',
        };
    }

    private function queueKeyFromRequest(Request $request, string $mode): string
    {
        $queue = trim((string) $request->query('queue', ''));

        return $queue !== ''
            ? TeamWorkQueues::normalize($queue)
            : $this->queueKeyForMode($mode);
    }

    private function stitchLabel(Order $order): string
    {
        return in_array((string) $order->order_type, ['vector', 'q-vector', 'color', 'qcolor'], true)
            ? 'hours'
            : 'stitches';
    }

    private function attachmentAbsolutePath(Attachment $attachment, Order $order, string $mode): string
    {
        $folder = match ((string) $attachment->file_source) {
            'orderTeamImages', 'color', 'edit order' => $mode === 'quote' ? 'quotes' : 'order',
            'team' => 'team',
            'sewout' => 'sewout',
            'scanned' => 'scanned',
            'quote', 'qquote' => 'quotes',
            'admin' => 'admin',
            default => 'order',
        };
        $fallbackFolders = in_array((string) $attachment->file_source, ['orderTeamImages', 'order', 'edit order', 'vector', 'color'], true)
            ? ['quotes']
            : [];

        return SharedUploads::firstExistingPath((string) $attachment->file_name_with_date, $folder, $fallbackFolders);
    }

    private function cleanFileName(string $fileName): string
    {
        $clean = UploadFileName::sanitize($fileName);

        return trim($clean) !== '' ? $clean : Str::random(12);
    }

    private function sendMailToAdmin(string $teamName, Order $order, string $subject): void
    {
        $body = view('team.orders.mail-admin-complete', [
            'teamName' => $teamName,
            'order' => $order,
        ])->render();

        PortalMailer::sendHtml((string) config('mail.admin_alert_address', ''), $subject, $body);
    }
}
