@include('customer.emails.layout', [
    'title' => 'New contact message',
    'siteLabel' => $siteContext->displayLabel(),
    'content' => view('customer.emails.partials.contact-message-body', [
        'payload' => $payload,
    ])->render(),
])
