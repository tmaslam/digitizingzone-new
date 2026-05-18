<?php

namespace App\Support;

use App\Models\EmailTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SystemEmailTemplates
{
    private const DEFINITIONS = [
        'customer_account_activation' => [
            'name' => 'Customer Account Activation',
            'usage' => 'Sent when a customer needs to verify and activate a new account.',
        ],
        'customer_password_reset' => [
            'name' => 'Customer Password Reset',
            'usage' => 'Sent after a customer requests a password reset link.',
        ],
        'customer_digitizing_order_confirmation' => [
            'name' => 'Customer Digitizing Order Confirmation',
            'usage' => 'Sent to the customer after a new digitizing order is submitted.',
        ],
        'customer_vector_order_confirmation' => [
            'name' => 'Customer Vector Order Confirmation',
            'usage' => 'Sent to the customer after a new vector order is submitted.',
        ],
        'customer_digitizing_quote_confirmation' => [
            'name' => 'Customer Digitizing Quote Confirmation',
            'usage' => 'Sent to the customer after a new digitizing quote is submitted.',
        ],
        'customer_vector_quote_confirmation' => [
            'name' => 'Customer Vector Quote Confirmation',
            'usage' => 'Sent to the customer after a new vector quote is submitted.',
        ],
        'customer_order_completed' => [
            'name' => 'Customer Order Completed',
            'usage' => 'Default completion email sent from admin order detail when an order is finished.',
        ],
        'customer_quote_completed' => [
            'name' => 'Customer Quote Completed',
            'usage' => 'Default completion email sent from admin order detail when a quote is finished.',
        ],
        'customer_quick_quote_completed' => [
            'name' => 'Customer Quick Quote Completed',
            'usage' => 'Default completion email sent from the admin quick quote detail screen.',
        ],
        'customer_quote_negotiation_response' => [
            'name' => 'Customer Quote Negotiation Response',
            'usage' => 'Sent after admin responds to a customer price negotiation on a quote.',
        ],
    ];

    private const TOKEN_LABELS = [
        '{{site_name}}' => 'Website / brand name',
        '{{site_label}}' => 'Display label for the active site',
        '{{support_email}}' => 'Support email address',
        '{{customer_name}}' => 'Customer full name',
        '{{customer_email}}' => 'Customer email address',
        '{{order_id}}' => 'Order or quote id',
        '{{design_name}}' => 'Design name',
        '{{order_type}}' => 'Order type label',
        '{{status}}' => 'Current order status',
        '{{format}}' => 'Requested format',
        '{{turnaround}}' => 'Turnaround label',
        '{{amount}}' => 'Formatted amount',
        '{{stitches}}' => 'Stitches or hours value',
        '{{body_label}}' => 'Label used for stitches or hours',
        '{{review_url}}' => 'Customer detail page link',
        '{{orders_url}}' => 'Customer orders page link',
        '{{quotes_url}}' => 'Customer quotes page link',
        '{{portal_url}}' => 'Customer dashboard link',
        '{{activation_url}}' => 'Account activation link',
        '{{reset_url}}' => 'Password reset link',
        '{{expires_at}}' => 'Human-readable expiration date/time',
        '{{payment_url}}' => 'Payment link used in quick quote email',
        '{{message}}' => 'Optional freeform message',
    ];

    public static function catalog(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'name' => $definition['name'],
                'usage' => $definition['usage'],
            ])
            ->values()
            ->all();
    }

    public static function tokenLabels(): array
    {
        return self::TOKEN_LABELS;
    }

    public static function templateName(string $key): string
    {
        return self::DEFINITIONS[$key]['name'] ?? $key;
    }

    public static function usage(string $key): string
    {
        return self::DEFINITIONS[$key]['usage'] ?? '';
    }

    public static function render(
        string $key,
        SiteContext $site,
        array $tokens,
        callable $fallback,
        ?array $override = null
    ): array {
        $tokens = self::normalizeTokens($site, $tokens);

        if ($override && trim((string) ($override['subject'] ?? '')) !== '' && trim((string) ($override['body'] ?? '')) !== '') {
            return [
                'subject' => self::replaceTokens((string) $override['subject'], $tokens),
                'body' => self::replaceTokens((string) $override['body'], $tokens),
                'template_name' => self::templateName($key).' (One-Time Override)',
                'from_template' => false,
            ];
        }

        $template = self::template($site, $key);
        if ($template) {
            return [
                'subject' => self::replaceTokens((string) $template->subject, $tokens),
                'body' => self::replaceTokens((string) $template->body, $tokens),
                'template_name' => (string) $template->template_name,
                'from_template' => true,
            ];
        }

        $default = $fallback($tokens);

        return [
            'subject' => (string) ($default['subject'] ?? ''),
            'body' => (string) ($default['body'] ?? ''),
            'template_name' => self::templateName($key),
            'from_template' => false,
        ];
    }

    public static function send(
        string $recipient,
        string $key,
        SiteContext $site,
        array $tokens,
        callable $fallback,
        ?array $override = null
    ): bool {
        $rendered = self::render($key, $site, $tokens, $fallback, $override);

        return PortalMailer::sendHtml($recipient, $rendered['subject'], $rendered['body']);
    }

    public static function usageForTemplateName(?string $templateName): ?string
    {
        $templateName = trim((string) $templateName);

        foreach (self::DEFINITIONS as $definition) {
            if ($definition['name'] === $templateName || str_starts_with($templateName, $definition['name'].' :: ') || str_starts_with($templateName, $definition['name'].' - ')) {
                return $definition['usage'];
            }
        }

        return null;
    }

    public static function selectionOptions(SiteContext $site, string $key): array
    {
        return self::matchingTemplates($site, $key)
            ->filter(fn (EmailTemplate $template) => (string) $template->template_name !== self::templateName($key))
            ->map(function (EmailTemplate $template) use ($key) {
                return [
                    'id' => (int) $template->id,
                    'label' => self::selectionLabelForTemplate($key, (string) $template->template_name),
                    'template_name' => (string) $template->template_name,
                ];
            })
            ->values()
            ->all();
    }

    public static function selectedTemplateOverride(SiteContext $site, string $key, ?int $templateId): ?array
    {
        if ($templateId === null || $templateId <= 0) {
            return null;
        }

        $template = self::matchingTemplates($site, $key)
            ->firstWhere('id', $templateId);

        if (! $template) {
            return null;
        }

        return [
            'subject' => (string) $template->subject,
            'body' => (string) $template->body,
        ];
    }

    private static function template(SiteContext $site, string $key): ?EmailTemplate
    {
        if (! Schema::hasTable('email_templates')) {
            return null;
        }

        $query = EmailTemplate::query()
            ->active()
            ->where('template_name', self::templateName($key));

        if (Schema::hasColumn('email_templates', 'site_id')) {
            $query->where(function ($inner) use ($site) {
                $inner->whereNull('site_id');

                if ($site->id !== null) {
                    $inner->orWhere('site_id', $site->id);
                }
            })->orderByRaw('CASE WHEN site_id IS NULL THEN 1 ELSE 0 END');
        }

        return $query->orderByDesc('id')->first();
    }

    private static function matchingTemplates(SiteContext $site, string $key): Collection
    {
        if (! Schema::hasTable('email_templates')) {
            return collect();
        }

        $baseName = self::templateName($key);

        $query = EmailTemplate::query()
            ->active()
            ->where(function ($inner) use ($baseName) {
                $inner->where('template_name', $baseName)
                    ->orWhere('template_name', 'like', $baseName.' :: %')
                    ->orWhere('template_name', 'like', $baseName.' - %');
            });

        if (Schema::hasColumn('email_templates', 'site_id')) {
            $query->where(function ($inner) use ($site) {
                $inner->whereNull('site_id');

                if ($site->id !== null) {
                    $inner->orWhere('site_id', $site->id);
                }
            })->orderByRaw('CASE WHEN site_id IS NULL THEN 1 ELSE 0 END');
        }

        return $query
            ->orderBy('template_name')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (EmailTemplate $template) => strtolower((string) $template->template_name))
            ->values();
    }

    private static function selectionLabelForTemplate(string $key, string $templateName): string
    {
        $baseName = self::templateName($key);

        if ($templateName === $baseName) {
            return 'Standard Template';
        }

        foreach ([' :: ', ' - '] as $separator) {
            if (str_starts_with($templateName, $baseName.$separator)) {
                return trim(substr($templateName, strlen($baseName.$separator)));
            }
        }

        return $templateName;
    }

    private static function normalizeTokens(SiteContext $site, array $tokens): array
    {
        $defaults = [
            '{{site_name}}' => $site->brandName ?: $site->name,
            '{{site_label}}' => $site->displayLabel(),
            '{{support_email}}' => $site->supportEmail,
        ];

        $normalized = [];
        foreach ($tokens as $key => $value) {
            $token = str_starts_with((string) $key, '{{') ? (string) $key : '{{'.$key.'}}';
            $normalized[$token] = (string) $value;
        }

        return array_merge($defaults, $normalized);
    }

    private static function replaceTokens(string $value, array $tokens): string
    {
        return strtr($value, $tokens);
    }
}
