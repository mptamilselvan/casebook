<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verify your email</title>
</head>
<body style="margin:0;background:#ffffff;color:#1f2937;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 12px;font-size:22px;">Verify your email</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#4b5563;">
                                Use this link to confirm {{ $user->email }} and open your Case Book dashboard. The link expires in 24 hours.
                            </p>
                            <p style="margin:0 0 20px;">
                                <a href="{{ $url }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;padding:12px 18px;font-weight:600;">
                                    Verify email
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.5;color:#6b7280;word-break:break-all;">
                                {{ $url }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
