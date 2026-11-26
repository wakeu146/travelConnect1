<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="0;url={{ $googleUrl }}">
    <title>Continue to Google</title>
    <script>
        window.location.replace(@json($googleUrl));
    </script>
</head>
<body>
    <p>Redirecting to Google sign-in...</p>
    <p><a href="{{ $googleUrl }}">Continue to Google</a></p>
</body>
</html>
