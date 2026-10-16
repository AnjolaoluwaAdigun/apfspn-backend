<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Your {{ $memberType->label() }} membership of APFSPN has been approved. Welcome to the association.</p>
    <p>Your member ID is <strong>{{ $memberId }}</strong>. Your membership is valid until {{ $expiresOn }}.</p>
    <p>You can sign in to the member portal at <a href="{{ $portalUrl }}">{{ $portalUrl }}</a> to see your membership details.</p>
    <p>APFSPN</p>
</body>
</html>
