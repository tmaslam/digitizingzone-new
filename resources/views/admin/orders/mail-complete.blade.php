<!DOCTYPE html>
<html lang="en">
<body style="font-family:Verdana, Geneva, sans-serif; font-size:13px;">
<p>Dear Customer,</p>

@if ($page === 'quote')
    <p>Your quotation with {{ $companyName }} has been completed.</p>
    <p>Please <a href="{{ $reviewUrl }}">review it in your account</a> at your convenience.</p>
@else
    <p>Your order with {{ $companyName }} has been completed.</p>
    <p>Please <a href="{{ $reviewUrl }}">review it in your account</a> at your convenience.</p>
@endif

<table cellpadding="6" cellspacing="0" border="0">
    <tr><td><strong>{{ $page === 'quote' ? 'Quotation' : 'Order' }} ID:</strong></td><td>{{ $order->order_id }}</td></tr>
    <tr><td><strong>Design Name:</strong></td><td>{{ $order->design_name }}</td></tr>
    <tr><td><strong>Amount:</strong></td><td>{{ $amount }}{{ $amount !== 'first order is free' ? ' USD' : '' }}</td></tr>
    <tr><td><strong>{{ $bodyLabel }}:</strong></td><td>{{ $stitches }}</td></tr>
</table>

<p>If you cannot open the link above, copy and paste this URL into your browser:<br><a href="{{ $reviewUrl }}">{{ $reviewUrl }}</a></p>

@if ($page === 'quote')
    <p><strong>PLEASE NOTE:</strong> This quotation is a preliminary estimate only. Final pricing may vary up to +/- 10% based on final design output. Should the cost exceed this range, we will notify you for approval prior to proceeding.</p>
@else
    <p><strong>DISCLAIMER:</strong> Please conduct a test run and verify the sample against your design before proceeding with production. 1dollardigitizing.com is not responsible for any damage to materials incurred during use. Designs are provided for lawful use only. The recipient assumes all responsibility for ensuring reproduction rights and maintaining compliance with intellectual property laws.</p>
@endif

<p>Kind regards,<br>{{ $companyName }}</p>
</body>
</html>
