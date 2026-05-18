@include('customer.emails.layout', [
    'title' => 'Activate your account',
    'siteLabel' => $siteContext->displayLabel(),
    'content' => view('customer.emails.partials.activation-body', [
        'customer' => $customer,
        'siteContext' => $siteContext,
        'signupOffer' => $signupOffer,
        'activationUrl' => $activationUrl,
        'expiresAt' => $expiresAt,
    ])->render(),
])
