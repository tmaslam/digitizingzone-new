<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TurnstileVerifier
{
    public static function enabled(): bool
    {
        return (bool) config('services.turnstile.enabled')
            && trim((string) config('services.turnstile.site_key', '')) !== ''
            && trim((string) config('services.turnstile.secret_key', '')) !== '';
    }

    public static function siteKey(): string
    {
        return trim((string) config('services.turnstile.site_key', ''));
    }

    public static function verify(Request $request, string $context): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $token = trim((string) $request->input('cf-turnstile-response', ''));
        if ($token === '') {
            SecurityAudit::recordBotVerificationFailure($request, $context, 'Turnstile response token was missing.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => trim((string) config('services.turnstile.secret_key', '')),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                    'idempotency_key' => (string) Str::uuid(),
                ]);

            if (! $response->ok()) {
                SecurityAudit::recordBotVerificationFailure($request, $context, 'Turnstile verification HTTP failure.', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                SecurityAudit::recordBotVerificationFailure($request, $context, 'Turnstile verification returned non-array payload.');

                return false;
            }

            $success = (bool) ($payload['success'] ?? false);
            if (! $success) {
                SecurityAudit::recordBotVerificationFailure($request, $context, 'Turnstile verification failed.', [
                    'error_codes' => $payload['error-codes'] ?? [],
                ]);
            }

            return $success;
        } catch (\Throwable $exception) {
            SecurityAudit::recordBotVerificationFailure($request, $context, 'Turnstile verification exception.', [
                'message' => $exception->getMessage(),
            ], 'error');

            return false;
        }
    }
}
