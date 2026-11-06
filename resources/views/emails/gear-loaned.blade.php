<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #222; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">Matériel prêté</h2>
        <p>Le matériel suivant vous a été prêté :</p>
        <ul style="padding-left: 20px;">
            @foreach($items as $item)
                <li>{{ $item['short_number'] ? $item['short_number'].' — ' : '' }}{{ $item['name'] }}</li>
            @endforeach
            @if($weightQuantity)
                <li>{{ $weightQuantity }} plomb(s)</li>
            @endif
        </ul>
        <p>Merci de le restituer à la fin de l'activité.</p>
    </div>

    <hr style="margin:30px 0;border-color:#ccc;max-width:560px">
    <p style="text-align:center;color:#666;font-size:12px">— EN —</p>

    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">Equipment loaned</h2>
        <p>The following equipment has been loaned to you:</p>
        <ul style="padding-left: 20px;">
            @foreach($items as $item)
                <li>{{ $item['short_number'] ? $item['short_number'].' — ' : '' }}{{ $item['name'] }}</li>
            @endforeach
            @if($weightQuantity)
                <li>{{ $weightQuantity }} weight(s)</li>
            @endif
        </ul>
        <p>Please return it at the end of the activity.</p>
    </div>
</body>
</html>
