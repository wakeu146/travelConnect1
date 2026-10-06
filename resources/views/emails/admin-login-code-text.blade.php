{{ __('messages.admin_login_code_heading') }}

{{ __('messages.hello_name', ['name' => $name]) }}
{{ __('messages.admin_login_code_intro') }}

{{ $code }}

{{ trans_choice('messages.admin_login_code_expiry', $expirationMinutes, ['minutes' => $expirationMinutes]) }}
{{ __('messages.admin_login_code_ignore') }}