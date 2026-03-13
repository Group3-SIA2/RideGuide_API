<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RideGuide Data Deletion Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem auto; max-width: 900px; line-height: 1.6; color: #1f2937; padding: 0 1rem; }
        h1 { color: #111827; }
    </style>
</head>
<body>
    <h1>Data Deletion Request Received</h1>

    @if ($confirmationCode !== '')
        <p>Your confirmation code is:</p>
        <p><strong>{{ $confirmationCode }}</strong></p>
    @else
        <p>Your deletion request has been received.</p>
    @endif

    <p>If you need assistance, contact <strong>support@rideguide.app</strong>.</p>
</body>
</html>
