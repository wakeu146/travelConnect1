<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.admin_users.security_email_subjects.'.$action) }} - TravelConnect</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f4f6f8;color:#172b35;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;margin:0 auto;background:#fff;border:1px solid #e7eaed;border-radius:12px;">
        <tr>
            <td style="padding:24px 32px;border-bottom:1px solid #edf0f2;">
                <p style="margin:0;color:#173042;font-size:22px;font-weight:700;">Travel<span style="color:#e76f51;font-weight:400;">Connect</span></p>
            </td>
        </tr>
        <tr>
            <td style="padding:36px 32px;">
                <p style="margin:0 0 8px;color:#e76f51;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">{{ __('messages.admin_users.security_email_eyebrow') }}</p>
                <h1 style="margin:0 0 20px;color:#172b35;font-size:26px;line-height:1.3;">{{ __('messages.admin_users.security_email_headings.'.$action) }}</h1>
                <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">{{ __('messages.admin_users.security_email_greeting', ['name' => $name]) }}</p>
                <p style="margin:0 0 24px;color:#59666e;font-size:15px;line-height:1.75;">{{ __('messages.admin_users.security_email_messages.'.$action) }}</p>
                <p style="margin:0;padding:14px 16px;background:#f8fafb;border-left:3px solid #e76f51;color:#59666e;font-size:13px;line-height:1.65;">
                    <strong>{{ __('messages.admin_users.security_email_time') }}:</strong> {{ $time }}
                </p>
                <p style="margin:24px 0 0;color:#59666e;font-size:14px;line-height:1.7;">{{ __('messages.admin_users.security_email_support') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 32px;background:#fafbfc;border-top:1px solid #edf0f2;color:#7b858b;font-size:12px;line-height:1.6;">
                {{ __('messages.admin_users.security_email_footer') }}
            </td>
        </tr>
    </table>
</body>
</html>
