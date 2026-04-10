<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher OTP Verification</title>
</head>
<body>
    <p>Hello {{ $teacher->full_name ?: $teacher->username }},</p>
    <p>Your OTP code for teacher account verification is:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{ $otpCode }}</p>
    <p>This code will expire in {{ $expiresInMinutes }} minutes.</p>
    <p>If you did not request this registration, you can ignore this email.</p>
</body>
</html>
