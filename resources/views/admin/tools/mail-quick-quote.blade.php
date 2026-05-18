<!DOCTYPE html>
<html lang="en">
<body style="font-family:Verdana, Geneva, sans-serif; font-size:13px;">
<p>Dear Customer,</p>
<p>Please note the following quote for your design:</p>
<p>{{ $order->design_name }} : {{ $stitches }} stitches : Price US{{ $amount }}</p>
<p>If you are happy with the price, please pay at the following link:</p>
<p><a href="{{ $paymentUrl }}">{{ $paymentUrl }}</a></p>
<p>Once payment is done, please do not forget to inform us by email.</p>
<p>Thanks and best regards,<br>CSR</p>
</body>
</html>
