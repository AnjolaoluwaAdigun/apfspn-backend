<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Your APFSPN membership{{ $memberId ? ' ('.$memberId.')' : '' }} has been suspended.</p>
    <p>Reason: {{ $reason }}</p>
    <p>If you believe this is a mistake or would like to resolve it, please reply to this email.</p>
    <p>APFSPN</p>
</body>
</html>
