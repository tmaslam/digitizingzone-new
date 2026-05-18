<?php

namespace App\Support;

use App\Models\Order;

class CustomerWorkflowStatus
{
    public static function label(Order|string|null $orderOrStatus, bool $isQuote = false): string
    {
        if ($orderOrStatus instanceof Order) {
            $status = strtolower(trim((string) $orderOrStatus->status));
            $isAssigned = ! in_array((string) $orderOrStatus->assign_to, ['', '0'], true) && $orderOrStatus->assign_to !== null;

            if ($status === 'underprocess' && ! $isAssigned) {
                return 'New';
            }
        }

        $status = $orderOrStatus instanceof Order
            ? strtolower(trim((string) $orderOrStatus->status))
            : strtolower(trim((string) $orderOrStatus));

        return match ($status) {
            'underprocess' => 'In Production',
            'ready' => $isQuote ? 'Quote In Review' : 'In Production',
            'done' => $isQuote ? 'Ready For Response' : 'Awaiting Your Approval',
            'approved' => 'Approved',
            'disapprove', 'disapproved' => $isQuote ? 'Feedback Sent' : 'Revision In Progress',
            default => $status === '' ? 'Pending' : ucwords(str_replace(['_', '-'], ' ', $status)),
        };
    }

    public static function tone(Order|string|null $orderOrStatus, bool $isQuote = false): string
    {
        $status = $orderOrStatus instanceof Order
            ? strtolower(trim((string) $orderOrStatus->status))
            : strtolower(trim((string) $orderOrStatus));

        return match ($status) {
            'done', 'disapprove', 'disapproved' => 'warning',
            'approved' => 'success',
            default => '',
        };
    }

    public static function actionHint(Order|string|null $orderOrStatus, bool $isQuote = false): string
    {
        if ($orderOrStatus instanceof Order) {
            $status = strtolower(trim((string) $orderOrStatus->status));
            $isAssigned = ! in_array((string) $orderOrStatus->assign_to, ['', '0'], true) && $orderOrStatus->assign_to !== null;

            if ($status === 'underprocess' && ! $isAssigned) {
                return $isQuote
                    ? 'This quote is new and waiting to be assigned for production review.'
                    : 'This order is new and waiting to be assigned for production.';
            }
        }

        $status = $orderOrStatus instanceof Order
            ? strtolower(trim((string) $orderOrStatus->status))
            : strtolower(trim((string) $orderOrStatus));

        return match ($status) {
            'done' => $isQuote ? 'Accept the quote to continue, or reject it with a reason if the pricing does not work for you.' : 'Review the completed work and approve it or request an edit.',
            'disapprove', 'disapproved' => $isQuote ? 'Your quote response has been sent and is waiting on review.' : 'An edit request is already back in the workflow.',
            'approved' => $isQuote ? 'This quote has been accepted.' : 'This order has moved into billing or archive.',
            default => $isQuote ? 'Open the quote to review files and pricing.' : 'Open the order to review files, comments, and delivery status.',
        };
    }
}
