{{ __('messages.admin_security_alert_heading') }}

{{ __('messages.hello_name', ['name' => $name]) }}
{{ $alertMessage }}
{{ __('messages.admin_security_alert_time') }}: {{ $time }}
{{ __('messages.admin_security_alert_ip') }}: {{ $ipAddress ?: __('messages.not_available') }}
{{ __('messages.admin_security_alert_device') }}: {{ $device ?: __('messages.not_available') }}

{{ __('messages.admin_security_alert_help') }}