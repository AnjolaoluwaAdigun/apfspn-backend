<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Good news: a seat opened up and you are now registered for <strong>{{ $eventTitle }}</strong>.</p>
    <p>When: {{ $startsAt }}<br>Where: {{ $location }}</p>
    @if ($virtualLink)
        <p>Join online: <a href="{{ $virtualLink }}">{{ $virtualLink }}</a></p>
    @endif
    <p>If you can no longer attend, please cancel from <a href="{{ $portalUrl }}">your member portal</a> so someone else can take the seat.</p>
    <p>APFSPN</p>
</body>
</html>
