<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body>
    <div style="margin:0;background:#f3f6f5;padding:32px 16px;color:#173042;font-family:Arial,Helvetica,sans-serif;">
        <div style="margin:0 auto;max-width:600px;overflow:hidden;border:1px solid #dfe7e4;border-radius:12px;background:#ffffff;">
            <div style="background:#173042;padding:22px 28px;color:#ffffff;">
                <p style="margin:0;color:#b9d8cf;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">TravelConnect</p>
                <h1 style="margin:10px 0 0;font-size:22px;line-height:1.4;">{{ $heading }}</h1>
            </div>
            <div style="padding:28px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">{{ __('messages.hello_name', ['name' => $agencyName]) }}</p>
                <p style="margin:0;color:#405b67;font-size:15px;line-height:1.8;">{{ $body }}</p>
                <div style="margin-top:24px;border-top:1px solid #e5ece9;padding-top:18px;">
                    <p style="margin:0;color:#607985;font-size:13px;line-height:1.7;">{{ __('messages.agency_moderation_email_contact') }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
