<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>Thank you for applying for {{ $memberType->label() }} membership of APFSPN{{ $organisationName ? ' on behalf of '.$organisationName : '' }}.</p>
    <p>Your application is now with our membership team for review. You can sign in to the member portal at any time to see its status, and we will email you once a decision has been made.</p>
    <p>APFSPN</p>
</body>
</html>
