<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.your_security_code_text') }} - TravelConnect</title>
</head>

<body
    style="margin:0;padding:0;background:#f4f6f8;color:#172b35;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">

    <!-- Preheader -->
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ __('messages.your_security_code_text') }} - TravelConnect
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="width:100%;background:#f4f6f8;">
        <tr>
            <td align="center" style="padding:48px 16px;">

                <!-- Main Container -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="width:100%;max-width:580px;background:#ffffff;border:1px solid #e7eaed;border-radius:12px;overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td style="padding:26px 36px;border-bottom:1px solid #edf0f2;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left">
                                        <p
                                            style="margin:0;color:#173042;font-size:22px;line-height:1;font-weight:700;letter-spacing:-0.5px;">
                                            Travel<span style="color:#e76f51;font-weight:400;">Connect</span>
                                        </p>
                                    </td>

                                    <td align="right">
                                        <span
                                            style="display:inline-block;padding:6px 10px;background:#fff4f1;color:#d85d43;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">
                                            Security
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding:44px 36px 38px;">

                            <!-- Security Icon -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                                style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" valign="middle"
                                        style="width:46px;height:46px;background:#fff4f1;border-radius:50%;">

                                        <span style="color:#e76f51;font-size:21px;line-height:46px;font-weight:700;">
                                            ✓
                                        </span>

                                    </td>
                                </tr>
                            </table>

                            <!-- Eyebrow -->
                            <p
                                style="margin:0 0 9px;color:#e76f51;font-size:12px;line-height:1.5;font-weight:700;letter-spacing:1px;text-transform:uppercase;">
                                {{ __('messages.account_security') }}
                            </p>

                            <!-- Title -->
                            <h1
                                style="margin:0 0 18px;color:#172b35;font-size:28px;line-height:1.25;font-weight:700;letter-spacing:-.6px;">
                                {{ __('messages.your_security_code_text') }}
                            </h1>

                            <!-- Greeting -->
                            <p style="margin:0 0 16px;color:#172b35;font-size:16px;line-height:1.6;">
                                {{ __('messages.hello_name', ['name' => $name]) }}
                            </p>

                            <!-- Message -->
                            <p style="margin:0 0 28px;color:#59666e;font-size:15px;line-height:1.75;">
                                {{ __('messages.security_code_message') }}
                            </p>

                            <!-- Code Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="width:100%;margin:0 0 24px;background:#f8fafb;border:1px solid #e3e8eb;border-radius:10px;">

                                <tr>
                                    <td align="center" style="padding:28px 20px 26px;">

                                        <p
                                            style="margin:0 0 10px;color:#7b858b;font-size:11px;line-height:1.4;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;">
                                            Security code
                                        </p>

                                        <span
                                            style="color:#173042;font-size:34px;line-height:1.2;font-weight:700;letter-spacing:9px;">
                                            {{ $code }}
                                        </span>

                                    </td>
                                </tr>
                            </table>

                            <!-- Expiration / Security Notice -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                style="width:100%;margin:0 0 26px;background:#fff9f7;border-left:3px solid #e76f51;">

                                <tr>
                                    <td style="padding:13px 16px;">
                                        <p style="margin:0;color:#68757c;font-size:13px;line-height:1.65;">
                                            {{ __('messages.code_expires_in_minutes', ['minutes' => $expiration]) }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Didn't Request -->
                            <p style="margin:0;color:#68757c;font-size:13px;line-height:1.7;">
                                {{ __('messages.if_you_did_not_request') }}
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 36px;background:#fafbfc;border-top:1px solid #edf0f2;">

                            <p style="margin:0 0 7px;color:#7b858b;font-size:12px;line-height:1.6;">
                                {{ __('messages.automated_security_message') }}
                            </p>

                            <p style="margin:0;color:#a0a7ac;font-size:12px;line-height:1.6;">
                                {{ __('messages.travelconnect_team') }}
                            </p>

                        </td>
                    </tr>

                </table>

                <!-- Bottom Brand -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="max-width:580px;">
                    <tr>
                        <td align="center" style="padding:22px 20px 0;">
                            <p style="margin:0;color:#a1a8ad;font-size:11px;line-height:1.5;">
                                Travel<span style="color:#e76f51;">Connect</span>
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>

</html>
