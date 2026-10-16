<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p><strong>{{ $eventTitle }}</strong> is fully booked, so you have been added to the waitlist{{ $waitlistPosition ? ' at position '.$waitlistPosition : '' }}.</p>
    <p>When: {{ $startsAt }}<br>Where: {{ $location }}</p>
    <p>We will email you straight away if a seat becomes available.</p>
    <p>APFSPN</p>
</body>
</html>
