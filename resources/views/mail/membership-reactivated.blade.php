<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Your APFSPN membership{{ $memberId ? ' ('.$memberId.')' : '' }} has been reactivated.</p>
    @if ($expiresOn)
        <p>Your membership is valid until {{ $expiresOn }}.</p>
    @endif
    <p>You can sign in to the member portal at <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>.</p>
    <p>APFSPN</p>
</body>
</html>
