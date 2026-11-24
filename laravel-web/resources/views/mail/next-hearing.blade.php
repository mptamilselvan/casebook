<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hearing reminder</title>
</head>
<body style="margin:0;background:#ffffff;color:#1f2937;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 12px;font-size:22px;">Hearing in {{ $whenLabel }}</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#4b5563;">
                                {{ $case->title }} is listed for hearing on {{ $case->next_hearing?->toDateString() }}.
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#111827;">
                                @if ($case->parties_label)
                                    <strong>Parties:</strong> {{ $case->parties_label }}<br>
                                @endif
                                <strong>Court:</strong> {{ $case->court }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
