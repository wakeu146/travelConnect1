<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body>
    <h1>{{ __('messages.admin_password_reset_notice_subject') }}</h1>
    <p>{{ __('messages.hello_name', ['name' => $name]) }}</p>
    <p>{{ __('messages.admin_password_reset_notice_body') }}</p>
</body>
</html>