<?php

namespace App\Support;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class StripeHostedCheckout
{
    public static function createSession(
        PaymentTransaction $transaction,
        Collection $checkoutItems,
        string $successUrl,
        string $cancelUrl,
        ?string $customerEmail = null
    ): array {
        $secretKey = trim((string) config('services.stripe.secret_key', ''));
        if ($secretKey === '') {
            return [
                'ok' => false,
                'message' => 'Stripe is not configured yet.',
            ];
        }

        $payload = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $transaction->merchant_reference,
            'metadata[merchant_reference]' => (string) $transaction->merchant_reference,
            'metadata[payment_transaction_id]' => (string) $transaction->id,
            'metadata[payment_scope]' => (string) $transaction->payment_scope,
            'metadata[legacy_website]' => (string) $transaction->legacy_website,
        ];

        if ($customerEmail !== null && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $payload['customer_email'] = $customerEmail;
        }

        $lineIndex = 0;
        foreach ($checkoutItems->values() as $item) {
            $amount = self::amountInCents((float) ($item['amount'] ?? 0));
            if ($amount <= 0) {
                continue;
            }

            $payload["line_items[$lineIndex][price_data][currency]"] = strtolower((string) ($transaction->currency ?: 'USD'));
            $payload["line_items[$lineIndex][price_data][unit_amount]"] = (string) $amount;
            $payload["line_items[$lineIndex][price_data][product_data][name]"] = trim((string) ($item['title'] ?? 'Payment'));
            $payload["line_items[$lineIndex][quantity]"] = '1';
            $lineIndex++;
        }

        if ($lineIndex === 0) {
            return [
                'ok' => false,
                'message' => 'No valid line items were available for Stripe checkout.',
            ];
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->asForm()
            ->post(rtrim((string) config('services.stripe.api_base', 'https://api.stripe.com/v1'), '/').'/checkout/sessions', $payload);

        if (! $response->successful()) {
            $message = trim((string) data_get($response->json(), 'error.message', ''));

            return [
                'ok' => false,
                'message' => $message !== '' ? $message : 'Stripe checkout session could not be created.',
                'payload' => $response->json(),
            ];
        }

        $session = $response->json();
        $sessionId = trim((string) ($session['id'] ?? ''));
        $redirectUrl = trim((string) ($session['url'] ?? ''));

        if ($sessionId === '' || $redirectUrl === '') {
            return [
                'ok' => false,
                'message' => 'Stripe did not return a usable checkout session.',
                'payload' => $session,
            ];
        }

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'redirect_url' => $redirectUrl,
            'payload' => $session,
        ];
    }

    public static function fetchSession(string $sessionId): array
    {
        $secretKey = trim((string) config('services.stripe.secret_key', ''));
        if ($secretKey === '' || trim($sessionId) === '') {
            return [
                'ok' => false,
                'message' => 'Stripe session lookup is not available.',
            ];
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->get(rtrim((string) config('services.stripe.api_base', 'https://api.stripe.com/v1'), '/').'/checkout/sessions/'.urlencode($sessionId), [
                'expand[]' => 'payment_intent',
            ]);

        if (! $response->successful()) {
            $message = trim((string) data_get($response->json(), 'error.message', ''));

            return [
                'ok' => false,
                'message' => $message !== '' ? $message : 'Stripe session lookup failed.',
                'payload' => $response->json(),
            ];
        }

        return [
            'ok' => true,
            'session' => $response->json(),
        ];
    }

    public static function parseWebhook(Request $request): array
    {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if (! self::verifyWebhookSignature($payload, $signature)) {
            return [
                'ok' => false,
                'payload' => $payload,
            ];
        }

        $event = json_decode($payload, true);

        return [
            'ok' => is_array($event),
            'event' => is_array($event) ? $event : null,
            'payload' => $payload,
        ];
    }

    public static function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $secret = trim((string) config('services.stripe.webhook_secret', ''));
        if ($secret === '' || trim($signatureHeader) === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $signatures = $parts['v1'] ?? [];
        if ($timestamp <= 0 || $signatures === []) {
            return false;
        }

        $tolerance = (int) config('services.stripe.webhook_tolerance', 300);
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function confirmedAmount(array $session): float
    {
        return round(((float) ($session['amount_total'] ?? 0)) / 100, 2);
    }

    public static function providerReference(array $session): string
    {
        $paymentIntent = $session['payment_intent'] ?? null;

        if (is_array($paymentIntent)) {
            return trim((string) ($paymentIntent['id'] ?? ''));
        }

        return trim((string) ($paymentIntent ?: ($session['id'] ?? '')));
    }

    private static function amountInCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
