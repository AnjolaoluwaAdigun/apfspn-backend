<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>You have been invited to the APFSPN admin dashboard. Set your password to activate your account:</p>
    <p><a href="{{ $link }}">Set your password</a></p>
    <p>This link expires in {{ $ttlHours }} hours.</p>
    <p>APFSPN</p>
</body>
</html>
