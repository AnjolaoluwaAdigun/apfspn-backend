<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hello {{ $name }},</p>
    <p>An editor has reviewed "{{ $title }}" and asked for changes before it can be published.</p>
    <p>Review note: {{ $reviewNote }}</p>
    <p><a href="{{ $articleUrl }}">Open the article</a> to make the changes and submit it again.</p>
    <p>APFSPN</p>
</body>
</html>
