<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>We are sorry to let you know that <strong>{{ $eventTitle }}</strong>, planned for {{ $startsAt }}, has been cancelled.</p>
    @if ($reason)
        <p>Reason: {{ $reason }}</p>
    @endif
    <p>APFSPN</p>
</body>
</html>
