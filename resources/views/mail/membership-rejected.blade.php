<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Thank you for applying for {{ $memberType->label() }} membership of APFSPN. After reviewing your application, our membership team was unable to approve it.</p>
    <p>Reason: {{ $reason }}</p>
    <p>If you have questions about this decision, please reply to this email.</p>
    <p>APFSPN</p>
</body>
</html>
