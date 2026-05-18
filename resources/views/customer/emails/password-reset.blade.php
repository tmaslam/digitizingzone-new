@include('customer.emails.layout', [
    'title' => 'Password reset request',
    'siteLabel' => $siteContext->displayLabel(),
    'content' => view('customer.emails.partials.password-reset-body', [
        'customer' => $customer,
        'siteContext' => $siteContext,
        'resetUrl' => $resetUrl,
        'expiresAt' => $expiresAt,
    ])->render(),
])
