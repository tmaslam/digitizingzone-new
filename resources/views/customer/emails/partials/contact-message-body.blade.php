<p style="margin-top:0;"><strong>Name:</strong> {{ $payload['name'] }}</p>
<p><strong>Email:</strong> {{ $payload['email'] }}</p>
<p><strong>Company:</strong> {{ $payload['company'] ?: '-' }}</p>
<p><strong>Phone:</strong> {{ $payload['phone'] ?: '-' }}</p>
<p><strong>IP Address:</strong> {{ $payload['ip_address'] ?? '-' }}</p>
<p><strong>Subject:</strong> {{ $payload['subject'] }}</p>
<p style="margin-bottom:0;"><strong>Message:</strong><br>{!! nl2br(e($payload['message'])) !!}</p>
