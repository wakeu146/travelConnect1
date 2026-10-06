<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body>
    <h1>{{ __('messages.admin_security_alert_heading') }}</h1>
    <p>{{ __('messages.hello_name', ['name' => $name]) }}</p>
    <p>{{ $alertMessage }}</p>
    <ul>
        <li>{{ __('messages.admin_security_alert_time') }}: {{ $time }}</li>
        <li>{{ __('messages.admin_security_alert_ip') }}: {{ $ipAddress ?: __('messages.not_available') }}</li>
        <li>{{ __('messages.admin_security_alert_device') }}: {{ $device ?: __('messages.not_available') }}</li>
    </ul>
    <p>{{ __('messages.admin_security_alert_help') }}</p>
</body>
</html>