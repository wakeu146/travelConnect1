<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body>
    <h1>{{ __('messages.admin_login_code_heading') }}</h1>
    <p>{{ __('messages.hello_name', ['name' => $name]) }}</p>
    <p>{{ __('messages.admin_login_code_intro') }}</p>
    <p><strong style="font-size: 28px; letter-spacing: 8px;">{{ $code }}</strong></p>
    <p>{{ trans_choice('messages.admin_login_code_expiry', $expirationMinutes, ['minutes' => $expirationMinutes]) }}</p>
    <p>{{ __('messages.admin_login_code_ignore') }}</p>
</body>
</html>