<p>Dear Admin,</p>

<p>A customer submitted a refund request from {{ $siteContext->displayLabel() }}.</p>

<p><strong>Customer ID:</strong> {{ $customer->user_id }}</p>
<p><strong>Customer Name:</strong> {{ $customer->display_name }}</p>
<p><strong>Customer Email:</strong> {{ $customer->user_email }}</p>
<p><strong>Reason:</strong> {{ $reason }}</p>
<p><strong>Date:</strong> {{ $submittedAt->format('Y-m-d H:i:s') }}</p>

<p>{{ $siteContext->displayLabel() }} Team</p>
