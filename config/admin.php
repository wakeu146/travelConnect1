<?php

return [
    'idle_timeout_minutes' => (int) env('ADMIN_IDLE_TIMEOUT_MINUTES', 20),
    'login_code_expires_minutes' => (int) env('ADMIN_LOGIN_CODE_EXPIRES_MINUTES', 1),
    'login_code_max_attempts' => (int) env('ADMIN_LOGIN_CODE_MAX_ATTEMPTS', 5),
    'password_lockout_attempts' => (int) env('ADMIN_PASSWORD_LOCKOUT_ATTEMPTS', 5),
    'password_lockout_minutes' => (int) env('ADMIN_PASSWORD_LOCKOUT_MINUTES', 15),
    'login_code_resend_seconds' => (int) env('ADMIN_LOGIN_CODE_RESEND_SECONDS', 60),
    'login_code_rate_limit_per_minute' => (int) env('ADMIN_LOGIN_CODE_RATE_LIMIT', 5),
    'trusted_device_cookie' => env('ADMIN_TRUSTED_DEVICE_COOKIE', 'travelconnect-admin-device'),
    'trusted_device_days' => (int) env('ADMIN_TRUSTED_DEVICE_DAYS', 90),
    'login_event_retention_days' => (int) env('ADMIN_LOGIN_EVENT_RETENTION_DAYS', 180),
    'failed_alert_threshold' => (int) env('ADMIN_FAILED_ALERT_THRESHOLD', 5),
    'failed_alert_window_minutes' => (int) env('ADMIN_FAILED_ALERT_WINDOW_MINUTES', 10),
    'failed_alert_cooldown_minutes' => (int) env('ADMIN_FAILED_ALERT_COOLDOWN_MINUTES', 30),
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
