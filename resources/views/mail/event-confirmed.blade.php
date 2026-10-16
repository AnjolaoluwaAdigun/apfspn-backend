<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>You are registered for <strong>{{ $eventTitle }}</strong>.</p>
    <p>When: {{ $startsAt }}<br>Where: {{ $location }}</p>
    @if ($virtualLink)
        <p>Join online: <a href="{{ $virtualLink }}">{{ $virtualLink }}</a></p>
    @endif
    <p>You can view or cancel your registration from <a href="{{ $portalUrl }}">your member portal</a>.</p>
    <p>APFSPN</p>
</body>
</html>
