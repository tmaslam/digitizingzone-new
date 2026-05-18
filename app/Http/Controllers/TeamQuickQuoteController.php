<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderComment;
use App\Support\AttachmentPreview;
use App\Support\DownstreamSharing;
use App\Support\PortalMailer;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeamQuickQuoteController extends Controller
{
    public function showByRoute(Request $request, Order $order)
    {
        $request->query->set('oid', (string) $order->order_id);
        $request->query->set('act', 'qquote');

        return $this->show($request);
    }

    public function show(Request $request)
    {
        abort_unless(Schema::hasTable('qucik_quote_users'), 404);

        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->query('oid'), $teamUser);
        abort_unless($order->order_type === 'qquote', 404);

        $editComment = $this->editableComment($request->query('edit_comment'), $order->order_id);

        return view('team.quick.show', [
            'teamUser' => $teamUser,
            'navCounts' => TeamNavigation::counts($teamUser->user_id, (int) $teamUser->usre_type_id),
            'order' => $order,
            'sharedComments' => DownstreamSharing::sharedComments($order->order_id),
            'teamComments' => OrderComment::query()->where('order_id', $order->order_id)->where('comment_source', 'team')->latest('id')->get(),
            'sharedAttachments' => Attachment::query()->where('order_id', $order->order_id)->whereIn('file_source', ['orderTeamImages', 'quote', 'vector', 'color', 'qquote'])->orderBy('id')->get(),
            'teamAttachments' => Attachment::query()->where('order_id', $order->order_id)->whereIn('file_source', ['team', 'sewout'])->orderBy('id')->get(),
            'teamCanComplete' => true,
            'editComment' => $editComment,
            'supervisorReviewComment' => OrderComment::query()->where('order_id', $order->order_id)->where('comment_source', 'supervisorReview')->latest('id')->first(),
            'backUrl' => $this->detailBackUrl(),
            'selfUrl' => $this->detailUrl($order),
            'currentQueueKey' => 'quick-quotes',
        ]);
    }

    public function saveComment(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_unless($order->order_type === 'qquote', 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'comment_id' => ['nullable', 'integer'],
            'comments' => ['required', 'string'],
        ], [], [
            'order_id' => 'quote',
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
                'source_page' => 'qquote',
                'comment_source' => 'team',
                'date_added' => $now,
                'date_modified' => $now,
            ]);

            $message = 'Comment added successfully.';
        }

        return redirect()->to($this->detailUrl($order))
            ->with('success', $message);
    }

    public function deleteComment(OrderComment $comment, Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $comment->order_id, $teamUser);
        abort_unless($order->order_type === 'qquote', 404);
        abort_unless($comment->comment_source === 'team', 404);

        $comment->delete();

        return redirect()->to($this->detailUrl($order))
            ->with('success', 'Comment deleted successfully.');
    }

    public function uploadAttachment(Request $request)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_unless($order->order_type === 'qquote', 404);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'files.*' => ['required', 'file', 'max:30720'],
        ], [
            'files.*.max' => 'Each file must be 30 MB or smaller.',
        ], [
            'order_id' => 'quote',
            'files' => 'files',
            'files.*' => 'file',
        ]);

        $files = $request->file('files', []);
        if ($files === []) {
            return redirect()->to($this->detailUrl($order))
                ->with('success', 'No files were selected.');
        }

        $uploadValidationError = UploadSecurity::assertAllowedFiles($files, 'production');
        if ($uploadValidationError !== null) {
            SecurityAudit::recordUploadRejected($request, 'production', 'Team production upload was rejected on a quick quote.', [
                'order_id' => $order->order_id,
                'file_names' => collect($files)
                    ->filter()
                    ->map(fn ($file) => $file->getClientOriginalName())
                    ->values()
                    ->all(),
            ]);
            return redirect()->to($this->detailUrl($order))
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

        return redirect()->to($this->detailUrl($order))
            ->with('success', 'Files uploaded successfully.');
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_unless($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['orderTeamImages', 'quote', 'vector', 'color', 'qquote', 'team', 'sewout'], true), 404);

        $path = $this->attachmentAbsolutePath($attachment);
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->file_name ?: basename($path));
    }

    public function previewAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_unless($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['orderTeamImages', 'quote', 'vector', 'color', 'qquote', 'team', 'sewout'], true), 404);

        $path = $this->attachmentAbsolutePath($attachment);
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
            'rawUrl' => url('/team/quick-attachments/'.$attachment->id.'/preview/raw'),
            'downloadUrl' => url('/team/quick-attachments/'.$attachment->id.'/download'),
            'backUrl' => $this->detailUrl($order),
            'textContent' => AttachmentPreview::kindForFileName($displayName) === 'text'
                ? AttachmentPreview::textContents($path)
                : null,
        ]);
    }

    public function deleteAttachment(Request $request, Attachment $attachment)
    {
        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $attachment->order_id, $teamUser);
        abort_unless($order->order_type === 'qquote', 404);
        abort_unless(in_array((string) $attachment->file_source, ['team', 'sewout'], true), 404);

        $path = $this->attachmentAbsolutePath($attachment);
        if (is_file($path)) {
            @unlink($path);
        }

        $attachment->delete();

        return redirect()->to($this->detailUrl($order))
            ->with('success', 'Attachment removed successfully.');
    }

    public function exportDesignInfo(Request $request)
    {
        abort_unless(Schema::hasTable('qucik_quote_users'), 404);

        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->query('design_id'), $teamUser);
        abort_unless($order->order_type === 'qquote', 404);

        $content = [];
        $content[] = 'Design ID: '.$order->order_id;
        $content[] = '';
        $content[] = 'Design Name: '.$order->design_name;
        $content[] = '';
        $content[] = 'Format: '.$order->format;
        $content[] = '';
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
        $content[] = 'Number Of Appliques: '.$order->no_of_appliques;
        $content[] = '';
        $content[] = 'Applique Colors: '.$order->applique_colors;
        $content[] = '';
        $content[] = 'Design Comments:';
        $content[] = '';
        foreach (DownstreamSharing::sharedComments($order->order_id) as $comment) {
            $content[] = (string) $comment->comments;
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
        abort_unless(Schema::hasTable('qucik_quote_users'), 404);

        $teamUser = $request->attributes->get('teamUser');
        $order = $this->teamOrderById((int) $request->input('order_id'), $teamUser);
        abort_unless($order->order_type === 'qquote', 404);

        if (! filled($order->sew_out)) {
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
            'stitches' => ['required', 'string'],
        ], [], [
            'order_id' => 'quote',
            'stitches' => 'stitches or hours',
        ]);

        $stitches = trim($validated['stitches']);
        if ($stitches === '' || (float) $stitches <= 0 && ! TeamPricing::isValidHours($stitches)) {
            return redirect()->to($this->detailUrl($order))
                ->withErrors(['stitches' => 'Please enter stitches, or total hours as a whole number or HH:MM, in order to complete this quote.']);
        }

        $pricing = PricingResolver::forTeamQuickQuote($order, $stitches);

        if (! $pricing['ok']) {
            return redirect()->to($this->detailUrl($order))
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

        $this->sendMailToAdmin($teamUser->display_name, $order, 'Quotation completed by '.$teamUser->user_name);

        return redirect()->to($this->detailBackUrl())
            ->with('success', 'You have successfully completed the quotation.');
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

    private function attachmentAbsolutePath(Attachment $attachment): string
    {
        $folder = match ((string) $attachment->file_source) {
            'orderTeamImages', 'edit order', 'qquote', 'quote', 'vector', 'color' => 'quotes',
            'team' => 'team',
            'sewout' => 'sewout',
            default => 'order',
        };

        return SharedUploads::path($folder.DIRECTORY_SEPARATOR.$attachment->file_name_with_date);
    }

    private function cleanFileName(string $fileName): string
    {
        $clean = UploadFileName::sanitize($fileName);

        return trim($clean) !== '' ? $clean : Str::random(12);
    }

    private function detailUrl(Order $order): string
    {
        return url('/team/quick-quotes/'.$order->order_id.'/detail');
    }

    private function detailBackUrl(): string
    {
        return TeamWorkQueues::url('quick-quotes');
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
