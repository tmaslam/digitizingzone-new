<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DownstreamSharing
{
    public static function sharedComments(int $orderId)
    {
        return OrderComment::query()
            ->where('order_id', $orderId)
            ->where('comment_source', 'orderTeamComments')
            ->orderBy('id')
            ->get();
    }

    public static function replaceSharedComments(Order $order, array $comments): void
    {
        OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'orderTeamComments')
            ->delete();

        $now = now()->format('Y-m-d H:i:s');

        foreach ($comments as $comment) {
            $text = trim((string) ($comment['comments'] ?? ''));
            if ($text === '') {
                continue;
            }

            OrderComment::query()->create([
                'order_id' => $order->order_id,
                'comments' => $text,
                'source_page' => (string) ($comment['source_page'] ?? 'orderTeamComments'),
                'comment_source' => 'orderTeamComments',
                'date_added' => $now,
                'date_modified' => $now,
            ]);
        }
    }

    public static function customerSubmissionText(Order $order): string
    {
        $chunks = [];

        foreach ([trim((string) $order->comments1), trim((string) $order->comments2)] as $comment) {
            if ($comment !== '') {
                $chunks[] = $comment;
            }
        }

        $customerComments = OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'customerComments')
            ->where('comments', '!=', '')
            ->orderBy('id')
            ->pluck('comments')
            ->map(fn ($comment) => trim((string) $comment))
            ->filter()
            ->all();

        $chunks = array_merge($chunks, $customerComments);

        $userNote = self::userNoteText($order);
        if ($userNote !== '') {
            $chunks[] = $userNote;
        }

        $unique = collect($chunks)
            ->map(fn ($comment) => preg_replace("/\r\n|\r|\n/", "\n", trim((string) $comment)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return implode("\n\n", $unique);
    }

    public static function existingSharedCustomerText(Order $order): string
    {
        return trim((string) OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'orderTeamComments')
            ->where('source_page', 'customer-shared')
            ->value('comments'));
    }

    public static function existingHandoffText(Order $order): string
    {
        $preferred = trim((string) OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'orderTeamComments')
            ->where('source_page', 'handoff')
            ->value('comments'));

        if ($preferred !== '') {
            return $preferred;
        }

        return trim((string) OrderComment::query()
            ->where('order_id', $order->order_id)
            ->where('comment_source', 'orderTeamComments')
            ->where('source_page', 'orderTeamComments')
            ->value('comments'));
    }

    private static function userNoteText(Order $order): string
    {
        if (! $order->notes_by_user || ! Schema::hasTable('usercomments')) {
            return '';
        }

        $notes = DB::table('usercomments')->where('userid', $order->user_id)->first();
        if (! $notes) {
            return '';
        }

        $isVector = in_array((string) $order->order_type, ['vector', 'q-vector', 'color', 'qcolor'], true);
        $comment = trim((string) ($isVector ? ($notes->vector_comment ?? '') : ($notes->digi_comment ?? '')));

        return $comment;
    }
}
