<table width="626" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td colspan="2" align="left" valign="top">
            <p>This is to inform you that the following job has been completed successfully.</p>
        </td>
    </tr>
    <tr>
        <td width="164" align="left"><strong>Order Id:</strong></td>
        <td width="462" align="left">{{ $order->order_id }}</td>
    </tr>
    <tr>
        <td align="left"><strong>Design Name:</strong></td>
        <td align="left">{{ $order->design_name }}</td>
    </tr>
    <tr>
        <td colspan="2" height="20"></td>
    </tr>
    <tr>
        <td colspan="2" align="left">
            With Best Regards,<br><br>
            {{ $teamName }}
        </td>
    </tr>
</table>
